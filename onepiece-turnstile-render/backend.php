<?php
// Render + external PostgreSQL API for the One Piece character spinner.
// Anonymous visitor identity is a signed, HttpOnly cookie. This prevents
// repeated votes by refresh/clicks, not determined Sybil attacks.
function reply(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function envValue(string $key): string { return (string)(getenv($key) ?: ''); }
function db(): PDO {
    static $db;
    if ($db) return $db;
    $url = envValue('DATABASE_URL');
    $parts = parse_url($url);
    if (!$parts || !isset($parts['host'],$parts['path'])) throw new RuntimeException('DATABASE_URL is missing or invalid');
    $params = [];
    if (isset($parts['query'])) parse_str($parts['query'], $params);
    $dsn = 'pgsql:host='.$parts['host'].';port='.($parts['port'] ?? 5432).';dbname='.ltrim($parts['path'],'/').';sslmode=require';
    $db = new PDO($dsn, urldecode($parts['user'] ?? ''), urldecode($parts['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec('CREATE TABLE IF NOT EXISTS posts (
        id TEXT PRIMARY KEY, owner_hash TEXT NOT NULL, name TEXT NOT NULL,
        body TEXT NOT NULL, stats JSONB NOT NULL,
        created_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS votes (
        post_id TEXT NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
        voter_hash TEXT NOT NULL, kind TEXT NOT NULL CHECK(kind IN (\'like\',\'dislike\')),
        PRIMARY KEY(post_id, voter_hash)
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS comments (
        id BIGSERIAL PRIMARY KEY, post_id TEXT NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
        author TEXT NOT NULL, body TEXT NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS activity (
        visitor_hash TEXT NOT NULL, kind TEXT NOT NULL,
        last_at TIMESTAMPTZ NOT NULL,
        PRIMARY KEY(visitor_hash,kind)
    )');
    return $db;
}
function visitor(): string {
    $secret = envValue('COOKIE_SECRET');
    if (strlen($secret) < 32) throw new RuntimeException('COOKIE_SECRET must have 32+ characters');
    // JS stores a random 256-bit key locally and supplies it with each API call.
    // Unlike third-party cookies, this works when the game is embedded in an iframe.
    $header = $_SERVER['HTTP_X_VISITOR_KEY'] ?? '';
    if (is_string($header) && preg_match('/^[a-f0-9]{64}$/', $header)) {
        return hash_hmac('sha256', $header, $secret);
    }
    $raw = $_COOKIE['op_vid'] ?? '';
    $pieces = explode('.', is_string($raw) ? $raw : '');
    $id = '';
    if (count($pieces) === 2 && preg_match('/^[a-f0-9]{40}$/', $pieces[0]) &&
        hash_equals(hash_hmac('sha256', $pieces[0], $secret), $pieces[1])) $id = $pieces[0];
    if (!$id) {
        $id = bin2hex(random_bytes(20));
        $value = $id . '.' . hash_hmac('sha256',$id,$secret);
        setcookie('op_vid', $value, [
            'expires'=>time()+365*86400,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly'=>true,'samesite'=>'Lax'
        ]);
    }
    return hash_hmac('sha256', $id, $secret);
}
function limited(PDO $db, string $visitor, string $kind, int $seconds): bool {
    // single atomic update prevents rapid repeat requests from the same visitor
    $stmt = $db->prepare("INSERT INTO activity(visitor_hash,kind,last_at) VALUES(?,?,now())
      ON CONFLICT(visitor_hash,kind) DO UPDATE SET last_at=now()
      WHERE activity.last_at < now() - (CAST(? AS integer) * interval '1 second')
      RETURNING visitor_hash");
    $stmt->execute([$visitor,$kind,$seconds]);
    return $stmt->fetchColumn() === false;
}
function input(): array {
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 25000) reply(['error'=>'Request too large'],413);
    $body = json_decode(file_get_contents('php://input') ?: '{}',true);
    if (!is_array($body)) reply(['error'=>'Invalid JSON'],400);
    return $body;
}
function textVal(mixed $value, int $max): string {
    if (!is_string($value)) return '';
    return mb_substr(trim($value),0,$max,'UTF-8');
}
function verifyPostOrigin(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($origin && parse_url($origin, PHP_URL_HOST) !== explode(':',$host)[0]) reply(['error'=>'Invalid request origin'],403);
}
// Require a one-use Cloudflare Turnstile token for public write actions.
function verifyCaptcha(array $body): void {
    $secret = envValue('TURNSTILE_SECRET_KEY');
    if (!$secret) reply(['error'=>'CAPTCHA is not configured on the server'],503);
    $token = $body['captcha_token'] ?? '';
    if (!is_string($token) || strlen($token)<1 || strlen($token)>2048)
        reply(['error'=>'Complete the CAPTCHA before submitting'],403);
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['secret'=>$secret,'response'=>$token]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = is_string($response) ? json_decode($response,true) : null;
    if ($status !== 200 || !is_array($data)) reply(['error'=>'CAPTCHA verification temporarily unavailable'],503);
    if (empty($data['success'])) reply(['error'=>'CAPTCHA expired or failed. Please try again'],403);
    // Explicitly check hostname, in addition to Turnstile's configured hostname restrictions.
    $expected = strtolower((string)explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
    if (!empty($data['hostname']) && strtolower((string)$data['hostname']) !== $expected)
        reply(['error'=>'CAPTCHA hostname mismatch'],403);
}
function fetchPosts(PDO $db, string $user): void {
    $sort = $_GET['sort'] ?? 'newest';
    $order = match($sort) {
        'likes' => 'score DESC, p.created_at DESC',
        'comments' => 'comment_count DESC, p.created_at DESC',
        default => 'p.created_at DESC',
    };
    $stmt = $db->prepare("SELECT p.id,p.name,p.body,p.stats,EXTRACT(EPOCH FROM p.created_at)::bigint AS timestamp,
      (p.owner_hash=?) AS is_mine,
      COALESCE(v.likes,0) AS likes, COALESCE(v.dislikes,0) AS dislikes,
      COALESCE(v.likes,0)-COALESCE(v.dislikes,0) AS score,
      COALESCE(c.comment_count,0) AS comment_count,
      uv.kind AS my_vote
      FROM posts p
      LEFT JOIN (SELECT post_id,count(*) FILTER (WHERE kind='like') AS likes,
       count(*) FILTER (WHERE kind='dislike') AS dislikes FROM votes GROUP BY post_id) v ON v.post_id=p.id
      LEFT JOIN (SELECT post_id,count(*) AS comment_count FROM comments GROUP BY post_id) c ON c.post_id=p.id
      LEFT JOIN votes uv ON uv.post_id=p.id AND uv.voter_hash=?
      ORDER BY $order LIMIT 200");
    $stmt->execute([$user,$user]);
    $rows=$stmt->fetchAll();
    $ids=array_column($rows,'id');
    $comments=[];
    if ($ids) {
        $params=implode(',',array_fill(0,count($ids),'?'));
        $q=$db->prepare("SELECT post_id,author,body,to_char(created_at,'Mon DD, HH24:MI') AS time
          FROM comments WHERE post_id IN ($params) ORDER BY created_at ASC LIMIT 3000");
        $q->execute($ids);
        foreach ($q as $r) $comments[$r['post_id']][]=['author'=>$r['author'],'text'=>$r['body'],'time'=>$r['time']];
    }
    $posts=[];
    foreach($rows as $r) {
        $posts[]=['id'=>$r['id'],'name'=>$r['name'],'text'=>$r['body'],
            'stats'=>is_string($r['stats']) ? json_decode($r['stats'],true) : $r['stats'],
            'likes'=>(int)$r['likes'],'dislikes'=>(int)$r['dislikes'],
            'timestamp'=>(int)$r['timestamp'],'comments'=>$comments[$r['id']] ?? [],
            'is_mine'=>$r['is_mine'] === true || $r['is_mine'] === 't',
            'my_vote'=>$r['my_vote']];
    }
    reply(['posts'=>$posts,'warning'=>false,'auto_cleared'=>false]);
}
try {
    $action = $_GET['action'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'health') reply(['status'=>'ok']);
    if (!$action) return;
    $user=visitor();
    $db=db();
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'fetch') fetchPosts($db,$user);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(['error'=>'Method not allowed'],405);
    verifyPostOrigin();
    $body=input();
    $id=textVal($body['id'] ?? '',80);
    if (in_array($action,['post','vote','comment'],true)) verifyCaptcha($body);
    if ($action === 'post') {
        $name=textVal($body['name'] ?? '',60);
        $msg=textVal($body['text'] ?? '',500);
        $stats=$body['stats'] ?? null;
        if (!$name || !is_array($stats) || count($stats)>35) reply(['error'=>'Invalid character'],400);
        $json=json_encode($stats);
        if (!$json || strlen($json)>12000) reply(['error'=>'Character too large'],400);
        if (limited($db,$user,'post',30)) reply(['error'=>'Please wait 30 seconds before another post'],429);
        $id=bin2hex(random_bytes(12));
        $q=$db->prepare('INSERT INTO posts(id,owner_hash,name,body,stats) VALUES(?,?,?,?,CAST(? AS jsonb))');
        $q->execute([$id,$user,$name,$msg,$json]);
        reply(['success'=>true,'id'=>$id]);
    }
    if ($action === 'delete') {
        $q=$db->prepare('DELETE FROM posts WHERE id=? AND owner_hash=?');
        $q->execute([$id,$user]);
        if (!$q->rowCount()) reply(['error'=>'Not your post or post does not exist'],403);
        reply(['success'=>true]);
    }
    if ($action === 'vote') {
        $type=textVal($body['type'] ?? '',10);
        if (!in_array($type,['like','dislike'],true)) reply(['error'=>'Invalid vote'],400);
        $q=$db->prepare('INSERT INTO votes(post_id,voter_hash,kind) VALUES(?,?,?) ON CONFLICT(post_id,voter_hash) DO UPDATE SET kind=EXCLUDED.kind');
        try {$q->execute([$id,$user,$type]);}
        catch (PDOException $e) { if ($e->getCode()==='23503') reply(['error'=>'Post not found'],404); throw $e; }
        reply(['success'=>true,'my_vote'=>$type]);
    }
    if ($action === 'comment') {
        $author=textVal($body['author'] ?? 'Anonymous',40) ?: 'Anonymous';
        $msg=textVal($body['text'] ?? '',500);
        if (!$msg) reply(['error'=>'Empty comment'],400);
        if (limited($db,$user,'comment',8)) reply(['error'=>'Please wait 8 seconds before commenting again'],429);
        $q=$db->prepare('INSERT INTO comments(post_id,author,body) VALUES(?,?,?)');
        try {$q->execute([$id,$author,$msg]);}
        catch (PDOException $e) { if ($e->getCode()==='23503') reply(['error'=>'Post not found'],404); throw $e; }
        reply(['success'=>true]);
    }
    reply(['error'=>'Unknown action'],404);
} catch (Throwable $e) {
    error_log('API error: '.$e->getMessage());
    reply(['error'=>'Server/database error. Check Render logs and DATABASE_URL.'],503);
}
