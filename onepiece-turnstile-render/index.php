<?php require __DIR__ . "/backend.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>One Piece Character Builder</title>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        body {
            background-color: #808080;
            font-family: "Tahoma", "MS Sans Serif", sans-serif;
            font-size: 11px;
            color: #000;
            margin: 15px;
        }
        .win98-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .win98-window {
            background-color: #c0c0c0;
            border: 2px solid;
            border-color: #ffffff #404040 #404040 #ffffff;
            box-shadow: 2px 2px 0px #000;
            margin-bottom: 15px;
            padding: 2px;
        }
        .win98-titlebar {
            background: #000080;
            color: #ffffff;
            font-weight: bold;
            padding: 3px 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .win98-titlebar span { font-size: 12px; }
        .win98-content { padding: 10px; }
        .win98-box {
            border: 2px solid;
            border-color: #808080 #ffffff #ffffff #808080;
            background: #ffffff;
            padding: 8px;
            margin-top: 5px;
        }
        button, .win98-btn {
            background: #c0c0c0;
            border: 2px solid;
            border-color: #ffffff #404040 #404040 #ffffff;
            font-family: "Tahoma", sans-serif;
            font-size: 11px;
            font-weight: bold;
            padding: 4px 10px;
            cursor: pointer;
        }
        button:active {
            border-color: #404040 #ffffff #ffffff #404040;
        }
        input[type="text"], select {
            border: 2px solid;
            border-color: #808080 #ffffff #ffffff #808080;
            padding: 3px;
            font-family: "Tahoma", sans-serif;
            font-size: 11px;
            background: #fff;
        }
        .warning-banner {
            background: #ff0000;
            color: #ffffff;
            font-weight: bold;
            padding: 6px;
            border: 2px solid #000;
            margin-bottom: 10px;
            text-align: center;
        }
        #wheelCanvas {
            max-width: 100%;
            height: auto;
            border: 2px solid #000;
            background-color: #fff;
            display: block;
            margin: 10px auto;
        }
        .pointer {
            width: 0; 
            height: 0; 
            border-left: 10px solid transparent;
            border-right: 10px solid transparent;
            border-top: 15px solid #ff0000;
            margin: 0 auto -12px auto;
            position: relative;
            z-index: 10;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 100;
            justify-content: center;
            align-items: center;
        }
        .modal-body {
            width: 320px;
            background: #c0c0c0;
            border: 2px solid;
            border-color: #ffffff #404040 #404040 #ffffff;
            box-shadow: 3px 3px 0 #000;
            padding: 2px;
        }
        .subtext {
            font-size: 10px;
            color: #404040;
            font-style: italic;
            display: block;
            margin-top: 6px;
        }
        .post-card {
            border: 2px solid;
            border-color: #808080 #ffffff #ffffff #808080;
            background: #f0f0f0;
            padding: 8px;
            margin-bottom: 10px;
        }
        .comment-box {
            background: #fff;
            border: 1px solid #808080;
            padding: 4px;
            margin-top: 4px;
            font-size: 10px;
        }
        table.stats-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.stats-table td {
            border: 1px solid #c0c0c0;
            padding: 4px;
        }
    </style>
</head>
<body onload="fetchChatPosts()">

<div class="win98-container">

    <div id="spaceWarning" class="warning-banner" style="display:none;">
        Warning: Storage limit is near capacity. Chat history will auto-clear soon.
    </div>

    <div class="win98-window">
        <div class="win98-titlebar">
            <span>One Piece Character Builder</span>
            <span>[X]</span>
        </div>
        <div class="win98-content">
            
            <div id="stepName">
                <fieldset style="border: 2px groove #fff; padding: 10px;">
                    <legend><b>Character Name</b></legend>
                    <label>Enter Name:</label><br><br>
                    <input type="text" id="charNameInput" style="width:250px;">
                    <button onclick="startWheelProcess()">Enter</button>
                </fieldset>
            </div>

            <div id="wheelContainer" style="display:none; text-align:center;">
                <h3 id="wheelTitle" style="margin:5px 0;">Wheel 1: Gender</h3>
                
                <div class="pointer"></div>
                <canvas id="wheelCanvas" width="320" height="320"></canvas>
                
                <button id="spinBtn" onclick="spinWheel()" style="font-size:12px; padding:6px 20px;">Spin</button>
            </div>

            <div id="summaryContainer" style="display:none;">
                <h3 style="margin-top:0;">Character Results</h3>
                <div class="win98-box" id="finalStatsBox"></div>
                <br>
                <div style="margin: 10px 0;">
                    <label><b>Post Message (optional):</b></label><br>
                    <input type="text" id="postTextInput" placeholder="Write something about your character..." style="width:100%; box-sizing:border-box; margin-top:3px;">
                </div>
                <button onclick="exportCharacterText()">Copy Text</button>
                <button onclick="exportCharacterJSON()">Export JSON</button>
                <button onclick="postToCommunityChat()">Post to Chat</button>
                <button onclick="resetBuilder()" style="float:right;">New Character</button>
            </div>

        </div>
    </div>

    <div class="win98-window">
        <div class="win98-titlebar">
            <span>Community Chat</span>
        </div>
        <div class="win98-content" id="chatContent">
            <div style="margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                <span>Sort by:</span>
                <select id="sortSelect" onchange="fetchChatPosts()">
                    <option value="newest">Newest</option>
                    <option value="likes">Most Liked</option>
                    <option value="comments">Most Comments</option>
                </select>
            </div>

            <div class="win98-box" style="margin-bottom:8px;">
                <b>Community verification</b>
                <p style="margin:5px 0;">Complete this check before posting, voting, or commenting. It refreshes after each action.</p>
                <?php if (getenv('TURNSTILE_SITE_KEY')): ?>
                <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars(getenv('TURNSTILE_SITE_KEY'), ENT_QUOTES, 'UTF-8') ?>" data-callback="onCaptchaSolved" data-expired-callback="onCaptchaExpired" data-error-callback="onCaptchaExpired" data-theme="light"></div>
                <?php else: ?>
                <p style="color:#a00000;"><b>CAPTCHA not configured:</b> set TURNSTILE_SITE_KEY on Render.</p>
                <?php endif; ?>
            </div>

            <div id="chatFeed" class="win98-box" style="max-height: 400px; overflow-y: auto;">
                Loading...
            </div>
        </div>
    </div>

</div>

<div class="modal-overlay" id="resultModal">
    <div class="modal-body">
        <div class="win98-titlebar">
            <span>Result</span>
        </div>
        <div style="padding:15px; text-align:center;">
            <h3 id="modalTitle" style="margin:5px 0; color:#000080;">Result</h3>
            <div id="modalSubtext" class="subtext"></div>
            <br><br>
            <button onclick="closeModalAndNext()" style="width:80px;">OK</button>
        </div>
    </div>
</div>

<script>
// Escape ALL community-supplied values before constructing HTML.
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
// A persistent anonymous identity for vote uniqueness and post ownership.
// It is generated locally and never needs a third-party cookie.
const visitorKey = (() => {
    try {
        let key = localStorage.getItem('op_visitor_key_v1');
        if (!/^[a-f0-9]{64}$/.test(key || '')) {
            key = Array.from(crypto.getRandomValues(new Uint8Array(32)),
                b => b.toString(16).padStart(2,'0')).join('');
            localStorage.setItem('op_visitor_key_v1', key);
        }
        return key;
    } catch (_) { return ''; }
})();
let captchaToken = '';
function onCaptchaSolved(token) { captchaToken = token; }
function onCaptchaExpired() { captchaToken = ''; }
function resetCaptcha() {
    captchaToken = '';
    if (window.turnstile) {
        try { window.turnstile.reset(); } catch (_) {}
    }
}
const apiRequest = async (action, payload) => {
    const protectedAction = ['post','vote','comment'].includes(action);
    if (protectedAction && !captchaToken) throw new Error('Complete the community CAPTCHA first.');
    if (protectedAction) payload = {...payload, captcha_token: captchaToken};
    try { return await sendApiRequest(action, payload); }
    finally { if (protectedAction) resetCaptcha(); }
};
const sendApiRequest = async (action, payload) => {
    const response = await fetch('index.php?action=' + encodeURIComponent(action), {
        method: 'POST', headers: {'Content-Type':'application/json', 'X-Visitor-Key':visitorKey},
        body: JSON.stringify(payload)
    });
    const json = await response.json();
    if (!response.ok || !json.success) throw new Error(json.error || 'Request failed');
    return json;
};
const safeId = value => /^[a-f0-9]{24}$/.test(value) ? value : '';
const devilFruitsList = [
    "Human-Human model: Nika", "Chop-Chop", "Tremor-Tremor", "Flower-Flower", "String-String", "Op-Op", "Slow-Slow", "Smooth-Smooth", "Bomb-Bomb", "Kilo-Kilo", "Wax-Wax", "Munch-Munch", "Clone-Clone", "Dice-Dice", "Spike-Spike", "Spring-Spring", "Hobby-Hobby", "Barrier-Barrier", "Arms-Arms", "Spin-Spin", "Sew-Sew", "Glare-Glare", "Pop-Pop", "Ton-Ton", "Swim-Swim", "Flutter-Flutter", "Stone-Stone", "Puff-Puff", "Memo-Memo", "Mochi-Mochi", "Straw-Straw", "Bis-Bis", "Butter-Butter", "Book-Book", "Cream-Cream", "Candy-Candy", "Heat-Heat (Oven)", "Juice-Juice", "Push-Push", "Snip-Snip", "Diamond-Diamond", "Pocket-Pocket", "Magnet-Magnet", "Soul-Soul", "Hollow-Hollow", "Shadow-Shadow", "Revive-Revive", "Paw-Paw", "Love-Love", "Venom-Venom", "Horm-Horm", "Rust-Rust", "Wheel-Wheel", "Berry-Berry", "Wash-Wash", "Mark-Mark", "Brain-Brain", "Sick-Sick", "Warp-Warp", "Strong-Strong", "Buff-Buff", "Glug-Glug", "Sound-Sound", "Door-Door", "Gold-Gold", "Luck-Luck", "Chain-Chain", "Cube-Cube", "Net-Net", "Through-Through", "Heat-Heat (Don Accino)", "Smoke-Smoke", "Sand-Sand", "Flame-Flame", "Rumble-Rumble", "Chilly-Chilly", "Dark-Dark", "Glint-Glint", "Magma-Magma", "Swamp-Swamp", "Gas-Gas", "Snow-Snow", "Forest-Forest", "Human-Human", "Ox-Ox (Bison)", "Ox-Ox (Giraffe)", "Mutt-Mutt (Jackal)", "Mutt-Mutt (Wolf)", "Cat-Cat (Leopard)", "Bird-Bird (Falcon)", "Elephant-Elephant", "Mole-Mole", "Horse-Horse", "Snake-Snake (Anaconda)", "Snake-Snake (King Cobra)", "Turtle-Turtle", "Beetle-Beetle", "Hornet-Hornet", "Dragon-Dragon (Allosaurus)", "Dragon-Dragon (Pteranodon)", "Dragon-Dragon (Brachiosaurus)", "Dragon-Dragon (Spinosaurus)", "Dragon-Dragon (Triceratops)", "Dragon-Dragon (Pachycephalosaurus)", "Elephant-Elephant (Mammoth)", "Human-Human (Buddha)", "Bird-Bird (Phoenix)", "Dog-Dog (Nine-Tailed Fox)", "Dog-Dog (Okuchi no Makami)", "Fish-Fish (Azure Dragon)", "Snake-Snake (Yamata no Orochi)", "Horse-Horse (Pegasus)", "Human-Human (Onyudo)", "Time-Time"
];

let characterState = {};
let currentWheelIndex = 0;
let isSpinning = false;

const wheels = [
    {
        id: "gender",
        title: "Wheel 1: Gender",
        options: [
            { label: "Male", weight: 48 },
            { label: "Female", weight: 48 },
            { label: "Transgender", weight: 4 }
        ]
    },
    {
        id: "race",
        title: "Wheel 2: Race",
        getOptions: () => {
            let fishLabel = "Fishman";
            if (characterState.gender === "Female") fishLabel = "Mermaid";
            if (characterState.gender === "Transgender") fishLabel = "Mer-person";
            return [
                { label: "Oni", weight: 5 },
                { label: "Mink", weight: 3 },
                { label: "Long leg", weight: 4 },
                { label: fishLabel, weight: 7 },
                { label: "Lunarian", weight: 1 },
                { label: "Giant", weight: 4 },
                { label: "Buccaneer", weight: 3 },
                { label: "Long Neck Tribe Member", weight: 1.5 },
                { label: "Long Arm Tribe Member", weight: 1.5 },
                { label: "Human", weight: 70 }
            ];
        }
    },
    {
        id: "haki",
        title: "Wheel 3: Haki",
        options: [
            { label: "Yes Haki", weight: 50 },
            { label: "No Haki", weight: 50 }
        ]
    },
    {
        id: "hakiTypes",
        title: "Wheel 4: Haki Types",
        shouldSkip: () => characterState.haki === "No Haki",
        options: [
            { label: "Conquerors only", weight: 3 },
            { label: "Conquerors and Armament", weight: 8 },
            { label: "Conquerors, Armament, and Observation", weight: 9 },
            { label: "Armament and Observation", weight: 40 },
            { label: "Observation haki only", weight: 20 },
            { label: "Armament haki only", weight: 20 }
        ]
    },
    {
        id: "conquerorsStrength",
        title: "Wheel 5: Conquerors Haki Strength",
        shouldSkip: () => !characterState.hakiTypes || !characterState.hakiTypes.includes("Conquerors"),
        options: [
            { label: "Shanks level", subtext: "(can knock out an admiral and stationary objects)", weight: 4 },
            { label: "Joyboy level", subtext: "(forces the gorosei back to mary geiose and had imu shaking in his boots)", weight: 2 },
            { label: "Ace level", subtext: "(never fully mastered)", weight: 40 },
            { label: "Garp Level", subtext: "(pretty darn strong)", weight: 4 },
            { label: "Doflamingo Level", subtext: "(not too impressive)", weight: 8 },
            { label: "Luffy level", subtext: "(increases as he gets stronger, cannot say currently because the feats will probably be drastically beaten)", weight: 2 },
            { label: "No advanced conquerors haki", weight: 40 }
        ]
    },
    {
        id: "observationStrength",
        title: "Wheel 6: Observation Haki Strength",
        shouldSkip: () => !characterState.hakiTypes || !characterState.hakiTypes.includes("Observation"),
        options: [
            { label: "Katakuri level", subtext: "(future sight)", weight: 33.33 },
            { label: "Enel level", subtext: "(predicting movements)", weight: 33.33 },
            { label: "Shanks level", subtext: "(kills other peoples observation haki)", weight: 33.34 }
        ]
    },
    {
        id: "armamentStrength",
        title: "Wheel 7: Armament Haki Strength",
        shouldSkip: () => !characterState.hakiTypes || !characterState.hakiTypes.includes("Armament"),
        options: [
            { label: "Sentomaru level", subtext: "(basic level, invisible armor and the counter to logia intangibility)", weight: 40 },
            { label: "Vergo level", subtext: "(covers whole body with armament hardening for extreme durability)", weight: 25 },
            { label: "Katakuri level", subtext: "(high density hardening to increase temperature)", weight: 20 },
            { label: "Garp level", subtext: "(extremely strong with internal destruction, and emission (ryou))", weight: 10 },
            { label: "Mihawk level", subtext: "(forged black blade due to extreme use of haki)", weight: 5 }
        ]
    },
    {
        id: "devilFruit",
        title: "Wheel 8: Devil Fruit",
        getOptions: () => {
            let opts = [{ label: "No Devil Fruit", weight: 50 }];
            devilFruitsList.forEach(df => {
                opts.push({ label: df, weight: 0.442 });
            });
            return opts;
        }
    },
    {
        id: "devilFruitAwakening",
        title: "Wheel 9: Devil Fruit Awakening",
        shouldSkip: () => characterState.devilFruit === "No Devil Fruit",
        options: [
            { label: "Awakened", weight: 40 },
            { label: "Not Awakened", weight: 60 }
        ]
    },
    {
        id: "physicalStrength",
        title: "Wheel 10: Physical Strength",
        options: [
            { label: "Big Mom Level", subtext: "(beat one of kaidos top men without haki or devil fruit due to amnesia)", weight: 10 },
            { label: "Garp Level", subtext: "(throws giant cannonballs at crazy speed even in his old age)", weight: 12 },
            { label: "Average Human", weight: 30 },
            { label: "Arlong Level", subtext: "(can flip houses and stuff)", weight: 20 },
            { label: "Jinbe Level", subtext: "(about ten times stronger in raw strength than an average human)", weight: 28 }
        ]
    },
    {
        id: "durability",
        title: "Wheel 11: Durability",
        options: [
            { label: "Franky Level", subtext: "(doesnt get damaged by bullets etc, but your characters body has an iron plate inside it)", weight: 2 },
            { label: "Whitebeard Level", subtext: "(fights just as strong even with severe injuries)", weight: 20 },
            { label: "Kid Nami Level", subtext: "(weakling)", weight: 15 },
            { label: "Usopp Level", weight: 43 },
            { label: "Kaido Level", subtext: "(doesnt take much damage even against extremely strong enemies)", weight: 20 }
        ]
    },
    {
        id: "speed",
        title: "Wheel 12: Speed",
        options: [
            { label: "Kid Nami Level", weight: 10 },
            { label: "Kizaru Level", subtext: "(you're speed of light without needing his devil fruit)", weight: 28 },
            { label: "Usopp Level", weight: 32 },
            { label: "Sanji Level", weight: 20 },
            { label: "Brook Level", subtext: "(you are now very light and can run on water)", weight: 10 }
        ]
    },
    {
        id: "weapon",
        title: "Wheel 13: Weapons / Items",
        options: [
            { label: "Ace", subtext: "(roger's sword)", weight: 1.25, isSword: true },
            { label: "Ame no Habakiri", subtext: "(the sword inherited to kozuki momonoske by oden)", weight: 2.5, isSword: true },
            { label: "Blow gun", weight: 2.5 },
            { label: "Kitetsu I", weight: 2.5, isSword: true },
            { label: "Kitetsu II", weight: 2.5, isSword: true },
            { label: "Kitetsu III", weight: 2.5, isSword: true },
            { label: "Enma", subtext: "(if you do not get above complete mastery at the next spinner you cannot use)", weight: 2.5, isSword: true },
            { label: "Brass knuckles", weight: 2.5 },
            { label: "Claws", weight: 2.5 },
            { label: "Dials", subtext: "(Impact, Reject, etc)", weight: 2.5 },
            { label: "Climatact", weight: 2.5 },
            { label: "Flame Thrower", weight: 2.5 },
            { label: "Pacifista laser", weight: 2.5 },
            { label: "Street pole", weight: 2.5 },
            { label: "Sea prism stone gun", subtext: "(cannot use if you have DF)", weight: 2.5 },
            { label: "Shield", weight: 2.5 },
            { label: "Shusui", weight: 2.5, isSword: true },
            { label: "Wado Ichimonji", weight: 2.5, isSword: true },
            { label: "Whatever whitebeards glave was called", weight: 1.25 },
            { label: "No weapon/item", weight: 55 }
        ]
    },
    {
        id: "weaponMastery",
        title: "Wheel 14: Weapon Mastery",
        shouldSkip: () => !characterState.weapon || characterState.weapon.label === "No weapon/item",
        getOptions: () => {
            const isSword = characterState.weapon && characterState.weapon.isSword;
            return [
                { label: "No mastery", weight: 15 },
                { label: "Complete mastery", weight: 30 },
                { label: "Basic mastery", weight: 45 },
                { label: "Extreme mastery", weight: isSword ? 9 : 10 },
                ...(isSword ? [{ label: "Black Blade", weight: 1 }] : [])
            ];
        }
    }
];

const canvas = document.getElementById('wheelCanvas');
const ctx = canvas.getContext('2d');
let currentAngle = 0;
let targetOption = null;

function getCurrentOptions(wheel) {
    return wheel.getOptions ? wheel.getOptions() : wheel.options;
}

function drawWheel(wheel, angle = 0) {
    const opts = getCurrentOptions(wheel);
    const totalWeight = opts.reduce((a, b) => a + b.weight, 0);
    const numSlices = opts.length;
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const radius = centerX - 10;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    let startAngle = angle;
    const palette = ['#d8d8d8', '#a8a8a8', '#f0f0f0', '#b8b8b8', '#e0e0e0', '#989898', '#cccccc', '#e8e8e8'];

    for (let i = 0; i < numSlices; i++) {
        const sliceAngle = (opts[i].weight / totalWeight) * Math.PI * 2;
        const endAngle = startAngle + sliceAngle;

        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.arc(centerX, centerY, radius, startAngle, endAngle);
        ctx.closePath();
        ctx.fillStyle = palette[i % palette.length];
        ctx.fill();
        ctx.stroke();

        ctx.save();
        ctx.translate(centerX, centerY);
        ctx.rotate(startAngle + sliceAngle / 2);
        ctx.textAlign = "right";
        ctx.fillStyle = "#000";
        ctx.font = "bold 9px Tahoma";
        let text = opts[i].label;
        if (text.length > 18) text = text.substring(0, 16) + '..';
        ctx.fillText(text, radius - 12, 4);
        ctx.restore();

        startAngle = endAngle;
    }
}

function selectWeightedOption(opts) {
    const totalWeight = opts.reduce((a, b) => a + b.weight, 0);
    let rand = Math.random() * totalWeight;
    for (let opt of opts) {
        if (rand < opt.weight) return opt;
        rand -= opt.weight;
    }
    return opts[0];
}

function startWheelProcess() {
    const name = document.getElementById('charNameInput').value.trim();
    if (!name) {
        alert("Enter a name first.");
        return;
    }
    characterState.name = name;
    document.getElementById('stepName').style.display = 'none';
    document.getElementById('wheelContainer').style.display = 'block';
    currentWheelIndex = 0;
    setupWheelIndex();
}

function setupWheelIndex() {
    if (currentWheelIndex >= wheels.length) {
        finishCharacter();
        return;
    }

    const currentWheel = wheels[currentWheelIndex];

    if (currentWheel.shouldSkip && currentWheel.shouldSkip()) {
        currentWheelIndex++;
        setupWheelIndex();
        return;
    }

    document.getElementById('wheelTitle').innerText = currentWheel.title;
    drawWheel(currentWheel, currentAngle);
}

function spinWheel() {
    if (isSpinning) return;
    isSpinning = true;

    const currentWheel = wheels[currentWheelIndex];
    const opts = getCurrentOptions(currentWheel);
    targetOption = selectWeightedOption(opts);

    const totalWeight = opts.reduce((a, b) => a + b.weight, 0);
    let accumulated = 0;
    let targetStartWeight = 0;
    let targetSliceWeight = 0;

    for (let i = 0; i < opts.length; i++) {
        if (opts[i] === targetOption) {
            targetStartWeight = accumulated;
            targetSliceWeight = opts[i].weight;
            break;
        }
        accumulated += opts[i].weight;
    }

    const randomSlicePercent = 0.10 + (Math.random() * 0.80);
    const targetWeightOffset = targetStartWeight + (targetSliceWeight * randomSlicePercent);

    const targetSliceAngle = (targetWeightOffset / totalWeight) * Math.PI * 2;
    const desiredFinalAngle = (Math.PI * 3 / 2) - targetSliceAngle;
    
    let extraSpins = Math.PI * 2 * (10 + Math.floor(Math.random() * 6));
    let finalAngle = currentAngle + extraSpins + (desiredFinalAngle - (currentAngle % (Math.PI * 2)));

    let start = null;
    let duration = 3800;

    function animateWheel(timestamp) {
        if (!start) start = timestamp;
        let progress = (timestamp - start) / duration;
        if (progress > 1) progress = 1;

        let ease = 1 - Math.pow(1 - progress, 5);
        let angleNow = currentAngle + (finalAngle - currentAngle) * ease;
        drawWheel(currentWheel, angleNow);

        if (progress < 1) {
            requestAnimationFrame(animateWheel);
        } else {
            currentAngle = angleNow;
            isSpinning = false;
            saveWheelResult(currentWheel, targetOption);
        }
    }

    requestAnimationFrame(animateWheel);
}

function saveWheelResult(wheel, result) {
    characterState[wheel.id] = result.label;
    if (wheel.id === "weapon") characterState.weapon = result;

    document.getElementById('modalTitle').innerText = result.label;
    document.getElementById('modalSubtext').innerText = result.subtext || '';
    document.getElementById('resultModal').style.display = 'flex';
}

function closeModalAndNext() {
    document.getElementById('resultModal').style.display = 'none';
    currentWheelIndex++;
    setupWheelIndex();
}

function finishCharacter() {
    document.getElementById('wheelContainer').style.display = 'none';
    document.getElementById('summaryContainer').style.display = 'block';

    let html = `<table class="stats-table">`;
    html += `<tr><td><b>Name</b></td><td>${esc(characterState.name)}</td></tr>`;
    html += `<tr><td><b>Gender</b></td><td>${characterState.gender || 'N/A'}</td></tr>`;
    html += `<tr><td><b>Race</b></td><td>${characterState.race || 'N/A'}</td></tr>`;
    html += `<tr><td><b>Haki</b></td><td>${characterState.haki || 'None'}</td></tr>`;
    if (characterState.hakiTypes) html += `<tr><td><b>Haki Types</b></td><td>${characterState.hakiTypes}</td></tr>`;
    if (characterState.conquerorsStrength) html += `<tr><td><b>Conquerors Haki</b></td><td>${characterState.conquerorsStrength}</td></tr>`;
    if (characterState.observationStrength) html += `<tr><td><b>Observation Haki</b></td><td>${characterState.observationStrength}</td></tr>`;
    if (characterState.armamentStrength) html += `<tr><td><b>Armament Haki</b></td><td>${characterState.armamentStrength}</td></tr>`;
    html += `<tr><td><b>Devil Fruit</b></td><td>${characterState.devilFruit || 'None'}</td></tr>`;
    if (characterState.devilFruitAwakening) html += `<tr><td><b>DF Awakening</b></td><td>${characterState.devilFruitAwakening}</td></tr>`;
    html += `<tr><td><b>Physical Strength</b></td><td>${characterState.physicalStrength || 'Average'}</td></tr>`;
    html += `<tr><td><b>Durability</b></td><td>${characterState.durability || 'Average'}</td></tr>`;
    html += `<tr><td><b>Speed</b></td><td>${characterState.speed || 'Average'}</td></tr>`;
    html += `<tr><td><b>Weapon/Item</b></td><td>${characterState.weapon ? characterState.weapon.label : 'None'}</td></tr>`;
    if (characterState.weaponMastery) html += `<tr><td><b>Weapon Mastery</b></td><td>${characterState.weaponMastery}</td></tr>`;
    html += `</table>`;

    document.getElementById('finalStatsBox').innerHTML = html;
}

function exportCharacterText() {
    let txt = `=== CHARACTER PROFILE ===\n`;
    for (let k in characterState) {
        if (typeof characterState[k] === 'object') continue;
        txt += `${k.toUpperCase()}: ${characterState[k]}\n`;
    }
    navigator.clipboard.writeText(txt);
    alert("Copied to clipboard.");
}

function exportCharacterJSON() {
    let blob = new Blob([JSON.stringify(characterState, null, 2)], {type : 'application/json'});
    let a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `${characterState.name}_character.json`;
    a.click();
}

function resetBuilder() {
    characterState = {};
    document.getElementById('summaryContainer').style.display = 'none';
    document.getElementById('stepName').style.display = 'block';
}

async function postToCommunityChat() {
    try {
        await apiRequest('post', {
            name: characterState.name,
            text: document.getElementById('postTextInput').value.trim(),
            stats: characterState
        });
        alert('Posted to community chat!');
        fetchChatPosts();
    } catch (error) { alert('Could not post: ' + error.message); }
}

function fetchChatPosts() {
    const sort = document.getElementById('sortSelect').value;

    fetch(`index.php?action=fetch&sort=${encodeURIComponent(sort)}`, {headers: {'X-Visitor-Key': visitorKey}})
        .then(res => res.json())
        .then(data => {
            const warningBanner = document.getElementById('spaceWarning');
            if (data.warning || data.auto_cleared) {
                warningBanner.style.display = 'block';
                if (data.auto_cleared) {
                    warningBanner.innerText = "Warning: Storage limit reached. Chat history was auto-cleared to recent posts.";
                }
            } else {
                warningBanner.style.display = 'none';
            }

            const feed = document.getElementById('chatFeed');
            if (!data.posts || data.posts.length === 0) {
                feed.innerHTML = '<div style="padding: 10px; text-align: center;">No characters posted yet. Be the first!</div>';
                return;
            }

            let html = '';
            data.posts.forEach(post => {
                const isMyPost = post.is_mine === true;
                if (!safeId(post.id)) return;
                let statsHtml = `<table class="stats-table" style="font-size:10px; margin-top:4px; margin-bottom:6px;">`;
                if (post.stats) {
                    for (let k in post.stats) {
                        if (typeof post.stats[k] === 'object') continue;
                        statsHtml += `<tr><td style="width:30%;"><b>${esc(k)}</b></td><td>${esc(post.stats[k])}</td></tr>`;
                    }
                }
                statsHtml += `</table>`;

                let commentsHtml = '';
                if (post.comments && post.comments.length > 0) {
                    post.comments.forEach(c => {
                        commentsHtml += `<div class="comment-box"><b>${esc(c.author)}</b> (${esc(c.time)}): ${esc(c.text)}</div>`;
                    });
                } else {
                    commentsHtml = `<div class="comment-box" style="color:#666; font-style:italic;">No comments yet.</div>`;
                }

                html += `
                    <div class="post-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #808080; padding-bottom:4px; margin-bottom:4px;">
                            <span><b>${esc(post.name)}</b> <span style="font-size:9px; color:#555;">(${new Date(post.timestamp * 1000).toLocaleString()})</span></span>
                            ${isMyPost ? `<button onclick="deletePost('${post.id}')" style="font-size:9px; padding:1px 4px;">Delete</button>` : ''}
                        </div>
                        ${post.text ? `<div style="margin-bottom:6px; font-style:italic;">"${esc(post.text)}"</div>` : ''}
                        <details style="margin-bottom:6px;">
                            <summary style="cursor:pointer; font-weight:bold; font-size:10px;">View Character Stats</summary>
                            ${statsHtml}
                        </details>
                        
                        <div style="display:flex; gap:6px; align-items:center; margin-bottom:6px; font-size:10px;">
                            <button onclick="votePost('${post.id}', 'like')" style="font-size:9px; padding:2px 6px;" ${post.my_vote === "like" ? "disabled" : ""}>👍 ${Number(post.likes) || 0}</button>
                            <button onclick="votePost('${post.id}', 'dislike')" style="font-size:9px; padding:2px 6px;" ${post.my_vote === "dislike" ? "disabled" : ""}>👎 ${Number(post.dislikes) || 0}</button>
                            <button onclick="toggleCommentsSection('${post.id}')" style="font-size:9px; padding:2px 6px;">💬 Comments (${post.comments ? post.comments.length : 0})</button>
                        </div>

                        <div id="comments-section-${post.id}" style="display:none; border-top:1px dashed #808080; padding-top:6px; margin-top:6px;">
                            <div style="max-height:150px; overflow-y:auto; margin-bottom:6px;">
                                ${commentsHtml}
                            </div>
                            <div style="display:flex; gap:4px;">
                                <input type="text" id="comment-author-${post.id}" placeholder="Your name" style="width:90px; font-size:10px;" value="Anonymous">
                                <input type="text" id="comment-text-${post.id}" placeholder="Write a comment..." style="flex:1; font-size:10px;" onkeypress="if(event.key === 'Enter') addComment('${post.id}')">
                                <button onclick="addComment('${post.id}')" style="font-size:9px; padding:2px 6px;">Send</button>
                            </div>
                        </div>
                    </div>
                `;
            });
            feed.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            document.getElementById('chatFeed').innerHTML = '<div style="color:red; padding:10px;">Error loading chat feed.</div>';
        });
}

function toggleCommentsSection(postId) {
    const sec = document.getElementById(`comments-section-${postId}`);
    if (sec) {
        sec.style.display = sec.style.display === 'none' ? 'block' : 'none';
    }
}

async function votePost(id, type) {
    if (!safeId(id)) return;
    try { await apiRequest('vote', {id, type}); fetchChatPosts(); }
    catch (error) { alert('Could not vote: ' + error.message); }
}

async function deletePost(id) {
    if (!safeId(id) || !confirm('Are you sure you want to delete this post?')) return;
    try { await apiRequest('delete', {id}); fetchChatPosts(); }
    catch (error) { alert('Could not delete: ' + error.message); }
}

async function addComment(id) {
    if (!safeId(id)) return;
    const authorInput = document.getElementById(`comment-author-${id}`);
    const textInput = document.getElementById(`comment-text-${id}`);
    const author = authorInput ? authorInput.value.trim() : 'Anonymous';
    const text = textInput ? textInput.value.trim() : '';
    if (!text) return;
    try { await apiRequest('comment', {id, author:author || 'Anonymous', text}); fetchChatPosts(); }
    catch (error) { alert('Could not comment: ' + error.message); }
}

</script>
</body>
</html>