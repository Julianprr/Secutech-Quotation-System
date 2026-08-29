<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecuTech Quotation System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f5f7;
            color: #222;
        }

        .header {
            background: #111;
            color: white;
            padding: 20px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            font-weight: bold;
            font-size: 20px;
        }

        .header-logo {
            width: 45px;
            height: auto;
            display: block;
        }

        .logout {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .container {
            max-width: 1100px;
            margin: 45px auto;
            padding: 0 25px;
        }

        .welcome {
            margin-bottom: 30px;
        }

        .welcome h2 {
            margin-bottom: 8px;
        }

        .welcome p {
            color: #666;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            text-decoration: none;
            color: #222;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(0,0,0,0.12);
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }

        .card p {
            color: #666;
            margin-bottom: 0;
            line-height: 1.5;
        }

        .icon {
            font-size: 30px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

<div class="header">

    <a href="dashboard.php" class="brand-link">
        <img src="assets/secutech-logo.png" alt="SecuTech SA" class="header-logo">
        <span>SecuTech Quoting System</span>
    </a>

    <a class="logout" href="login.php">
        Logout
    </a>

</div>

<div class="container">

    <div class="welcome">

        <h2>Dashboard</h2>

        <p>
            Welcome, <?= htmlspecialchars($user_name) ?>.
        </p>

    </div>

    <div class="cards">

        <!-- CUSTOMERS -->

        <a class="card" href="customers/index.php">

            <div class="icon">👥</div>

            <h3>Customers</h3>

            <p>
                Add, edit and manage your customers.
            </p>

        </a>


        <!-- PRODUCTS / ITEMS -->

        <a class="card" href="items/index.php">

            <div class="icon">📦</div>

            <h3>Products / Items</h3>

            <p>
                Manage products, item codes, prices and VAT rates.
            </p>

        </a>


        <!-- NEW QUOTATION -->

        <a class="card" href="create/index.php">

            <div class="icon">📝</div>

            <h3>New Quotation</h3>

            <p>
                Create a new quotation for a customer.
            </p>

        </a>


        <!-- QUOTATIONS -->

        <a class="card" href="view/list.php">

            <div class="icon">📄</div>

            <h3>Quotations</h3>

            <p>
                View and manage existing quotations.
            </p>

        </a>


        <!-- INVOICES -->

        <a class="card" href="invoices/list.php">

            <div class="icon">🧾</div>

            <h3>Invoices</h3>

            <p>
                View invoices and track payment status.
            </p>

        </a>


        <!-- AI ASSISTANT -->

        <button type="button" class="card" onclick="toggleAssistant()" style="text-align:left; cursor:pointer; font-family:inherit; font-size:inherit; border:none; background:white; width:100%; box-sizing:border-box; display:block;">

            <div class="icon">🎙️</div>

            <h3>AI Assistant</h3>

            <p>
                Talk or type to build and amend quotes hands-free.
            </p>

        </button>

    </div>

</div>


<!-- ============================================
     AI ASSISTANT WIDGET
============================================ -->

<button id="assistantToggle" onclick="toggleAssistant()">
    💬 Ask Assistant
</button>

<div id="assistantPanel">

    <div id="assistantHeader">
        <span>Quotation Assistant</span>
        <div style="display:flex; align-items:center; gap:12px;">
            <button onclick="toggleHandsFree()" id="assistantHandsFreeToggle" title="Hands-free mode - listens automatically after each reply">🎧</button>
            <button onclick="toggleVoiceReply()" id="assistantVoiceToggle" title="Toggle spoken replies">🔊</button>
            <button onclick="toggleAssistant()" id="assistantClose">&times;</button>
        </div>
    </div>

    <div id="assistantMessages">
        <div class="assistant-msg assistant-bot">
            Hi! Tell me about a quote you'd like to create - for example,
            "New quote for Acme Corp, 3 HD cameras at R850 each" - and I'll
            help you build it.
        </div>
    </div>

    <div id="assistantInputRow">
        <input
            type="text"
            id="assistantInput"
            placeholder="Type or tap the mic to talk..."
            onkeydown="if(event.key==='Enter') sendAssistantMessage()"
        >
        <button id="assistantMicBtn" onclick="toggleListening()" title="Voice input">
            🎤
        </button>
        <button onclick="sendAssistantMessage()">Send</button>
    </div>

</div>

<style>

#assistantToggle {
    position: fixed;
    bottom: 25px;
    right: 25px;
    background: #172d4d;
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 30px;
    font-size: 15px;
    cursor: pointer;
    box-shadow: 0 5px 20px rgba(0,0,0,0.25);
    z-index: 999;
}

#assistantToggle:hover {
    background: #263f61;
}

#assistantPanel {
    display: none;
    position: fixed;
    bottom: 90px;
    right: 25px;
    width: 380px;
    max-width: calc(100vw - 40px);
    height: 520px;
    max-height: calc(100vh - 140px);
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.25);
    flex-direction: column;
    overflow: hidden;
    z-index: 999;
}

#assistantHeader {
    background: #172d4d;
    color: white;
    padding: 14px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: bold;
}

#assistantClose {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
    line-height: 1;
}

#assistantMessages {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f4f5f7;
}

.assistant-msg {
    padding: 10px 13px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.4;
    max-width: 85%;
    white-space: pre-wrap;
}

.assistant-bot {
    background: white;
    color: #222;
    align-self: flex-start;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.assistant-user {
    background: #172d4d;
    color: white;
    align-self: flex-end;
}

.assistant-error {
    background: #ffe1e1;
    color: #a00000;
    align-self: flex-start;
}

.assistant-link {
    display: inline-block;
    margin-top: 8px;
    background: #b5d900;
    color: #172d4d;
    padding: 7px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    font-size: 13px;
}

#assistantInputRow {
    display: flex;
    gap: 8px;
    padding: 12px;
    border-top: 1px solid #eee;
}

#assistantInputRow input {
    flex: 1;
    padding: 10px 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
}

#assistantInputRow button {
    background: #172d4d;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    cursor: pointer;
}

#assistantMicBtn {
    background: #eee !important;
    color: #222;
    font-size: 16px;
    padding: 10px 13px !important;
}

#assistantMicBtn.listening {
    background: #ff4d4d !important;
    color: white;
    animation: assistantPulse 1.2s infinite;
}

#assistantVoiceToggle {
    background: none;
    border: none;
    color: white;
    font-size: 16px;
    cursor: pointer;
    padding: 0;
}

#assistantHandsFreeToggle {
    background: none;
    border: none;
    color: white;
    font-size: 16px;
    cursor: pointer;
    padding: 3px 6px;
    border-radius: 5px;
    opacity: 0.6;
}

#assistantHandsFreeToggle.active {
    background: #b5d900;
    opacity: 1;
}

@keyframes assistantPulse {
    0% { box-shadow: 0 0 0 0 rgba(255,77,77,0.5); }
    70% { box-shadow: 0 0 0 10px rgba(255,77,77,0); }
    100% { box-shadow: 0 0 0 0 rgba(255,77,77,0); }
}

@media (max-width: 500px) {
    #assistantPanel {
        right: 10px;
        left: 10px;
        width: auto;
    }
    #assistantToggle {
        right: 15px;
        bottom: 15px;
    }
}

</style>

<script>

let assistantHistory = [];
let assistantOpen = false;
let voiceReplyEnabled = true;
let handsFreeMode = false;
let speechRecognizer = null;
let listening = false;


/* -------------------------------------------------
   VOICE INPUT (microphone -> text)
------------------------------------------------- */

function getSpeechRecognition() {
    return window.SpeechRecognition || window.webkitSpeechRecognition || null;
}

function ensureRecognizer() {

    const SpeechRecognitionClass = getSpeechRecognition();

    if (!SpeechRecognitionClass) {
        return null;
    }

    if (speechRecognizer) {
        return speechRecognizer;
    }

    speechRecognizer = new SpeechRecognitionClass();
    speechRecognizer.continuous = false;
    speechRecognizer.interimResults = true;
    speechRecognizer.lang = 'en-ZA';

    speechRecognizer.onresult = function (event) {
        let transcript = '';
        for (let i = 0; i < event.results.length; i++) {
            transcript += event.results[i][0].transcript;
        }
        document.getElementById('assistantInput').value = transcript;
    };

    speechRecognizer.onend = function () {
        listening = false;
        document.getElementById('assistantMicBtn').classList.remove('listening');

        const text = document.getElementById('assistantInput').value.trim();

        if (text) {
            sendAssistantMessage();
        } else if (handsFreeMode) {
            /* Nothing heard - if hands-free is still on, try listening again shortly. */
            setTimeout(function () {
                if (handsFreeMode) startListening();
            }, 800);
        }
    };

    speechRecognizer.onerror = function (event) {
        listening = false;
        document.getElementById('assistantMicBtn').classList.remove('listening');

        if (event.error !== 'no-speech' && event.error !== 'aborted') {
            appendAssistantMessage('Voice input error: ' + event.error, 'assistant-error');
        } else if (handsFreeMode && event.error === 'no-speech') {
            setTimeout(function () {
                if (handsFreeMode) startListening();
            }, 800);
        }
    };

    return speechRecognizer;
}

function startListening() {

    const recognizer = ensureRecognizer();

    if (!recognizer) {
        appendAssistantMessage(
            'Voice input isn\'t supported in this browser. Try Chrome on desktop or Android.',
            'assistant-error'
        );
        return;
    }

    if (listening) {
        return;
    }

    document.getElementById('assistantInput').value = '';
    recognizer.start();
    listening = true;
    document.getElementById('assistantMicBtn').classList.add('listening');

}

function stopListening() {

    if (speechRecognizer && listening) {
        speechRecognizer.stop();
    }

}

function toggleListening() {

    if (listening) {
        stopListening();
    } else {
        startListening();
    }

}


/* -------------------------------------------------
   HANDS-FREE MODE
   Keeps listening automatically after every reply,
   without needing to tap the mic each time.
------------------------------------------------- */

function toggleHandsFree() {

    handsFreeMode = !handsFreeMode;

    const btn = document.getElementById('assistantHandsFreeToggle');
    btn.classList.toggle('active', handsFreeMode);

    if (handsFreeMode) {

        appendAssistantMessage(
            'Hands-free mode is on. I\'ll keep listening after each reply - tap the headset again to stop.',
            'assistant-bot'
        );

        startListening();

    } else {

        stopListening();

        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }

    }

}


/* -------------------------------------------------
   VOICE OUTPUT (text -> speech)
------------------------------------------------- */

function toggleVoiceReply() {

    voiceReplyEnabled = !voiceReplyEnabled;

    document.getElementById('assistantVoiceToggle').textContent =
        voiceReplyEnabled ? '🔊' : '🔇';

    if (!voiceReplyEnabled && window.speechSynthesis) {
        window.speechSynthesis.cancel();
    }

}

function sanitizeForSpeech(text) {

    let clean = text;

    /* Strip markdown formatting the model might still slip in */
    clean = clean.replace(/\*\*(.*?)\*\*/g, '$1');
    clean = clean.replace(/\*(.*?)\*/g, '$1');
    clean = clean.replace(/`(.*?)`/g, '$1');
    clean = clean.replace(/^#{1,6}\s*/gm, '');
    clean = clean.replace(/^[-*+]\s+/gm, '');
    clean = clean.replace(/^\d+\.\s+/gm, '');
    clean = clean.replace(/[*_#`~]/g, '');

    /* Make South African Rand amounts read naturally, e.g. "R850" -> "850 rand" */
    clean = clean.replace(/\bR\s?(\d[\d,]*(?:\.\d{2})?)/g, '$1 rand');

    /* Spoken-friendlier punctuation */
    clean = clean.replace(/&/g, ' and ');
    clean = clean.replace(/%/g, ' percent');
    clean = clean.replace(/;/g, ',');

    /* Collapse extra whitespace left behind by the replacements above */
    clean = clean.replace(/[ \t]+/g, ' ').replace(/\n{2,}/g, '. ').trim();

    return clean;

}

function speakAssistantReply(text, onDone) {

    if (!voiceReplyEnabled || !window.speechSynthesis) {
        if (onDone) onDone();
        return;
    }

    window.speechSynthesis.cancel();

    const spokenText = sanitizeForSpeech(text);

    const utterance = new SpeechSynthesisUtterance(spokenText);
    utterance.lang = 'en-ZA';

    utterance.onend = function () {
        if (onDone) onDone();
    };

    utterance.onerror = function () {
        if (onDone) onDone();
    };

    window.speechSynthesis.speak(utterance);

}

function toggleAssistant() {
    assistantOpen = !assistantOpen;
    document.getElementById('assistantPanel').style.display = assistantOpen ? 'flex' : 'none';

    if (assistantOpen) {

        document.getElementById('assistantInput').focus();

    } else {

        /* Closing the panel - stop any active mic/speech so it doesn't run in the background. */

        if (handsFreeMode) {
            handsFreeMode = false;
            const btn = document.getElementById('assistantHandsFreeToggle');
            if (btn) btn.classList.remove('active');
        }

        stopListening();

        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }

    }
}

function appendAssistantMessage(text, cssClass, linkUrl) {
    const container = document.getElementById('assistantMessages');
    const div = document.createElement('div');
    div.className = 'assistant-msg ' + cssClass;
    div.textContent = text;

    if (linkUrl) {
        const link = document.createElement('a');
        link.href = linkUrl;
        link.className = 'assistant-link';
        link.textContent = 'View Quotation →';
        div.appendChild(document.createElement('br'));
        div.appendChild(link);
    }

    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

async function sendAssistantMessage() {

    const input = document.getElementById('assistantInput');
    const text = input.value.trim();

    if (!text) return;

    appendAssistantMessage(text, 'assistant-user');
    input.value = '';

    assistantHistory.push({ role: 'user', content: text });

    appendAssistantMessage('Thinking...', 'assistant-bot');
    const messages = document.getElementById('assistantMessages');
    const thinkingBubble = messages.lastChild;

    try {

        const response = await fetch('assistant/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ messages: assistantHistory }),
        });

        const data = await response.json();

        thinkingBubble.remove();

        if (data.error) {
            appendAssistantMessage(data.error, 'assistant-error');
            return;
        }

        assistantHistory = data.messages || assistantHistory;

        const linkUrl = data.quotation_id
            ? 'view/index.php?id=' + data.quotation_id
            : null;

        appendAssistantMessage(data.reply, 'assistant-bot', linkUrl);

        speakAssistantReply(data.reply, function () {
            if (handsFreeMode) {
                setTimeout(function () {
                    if (handsFreeMode) startListening();
                }, 400);
            }
        });

    } catch (err) {

        thinkingBubble.remove();
        appendAssistantMessage('Could not reach the assistant. Please try again.', 'assistant-error');

    }

}

</script>

</body>
</html>