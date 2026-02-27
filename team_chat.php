<?php
require 'includes/config.php';
require_role(['bank_staff','bank_admin']);

$page_title='Team Chat';
ob_start();
?>
<div class="page-wrap" style="max-width:760px">
  <div class="page-header">
    <h1>💬 Team Chat</h1>
    <p>Real-time communication — messages are not saved to the database.</p>
  </div>

  <div class="card">
    <div id="chat-box" class="chat-box">
      <div style="text-align:center;color:var(--muted);font-size:13px;padding:20px 0" id="empty-msg">
        No messages yet. Start the conversation!
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:14px">
      <input id="msg-input" class="form-control" placeholder="Type a message… (Enter to send)" style="flex:1">
      <button id="send-btn" class="btn btn-primary">Send</button>
    </div>
    <div class="text-xs text-muted" style="margin-top:8px">
      ⚠ Messages exist only in this browser session — they are not stored anywhere.
    </div>
  </div>
</div>

<script>
const ME = '<?=addslashes(clean($_SESSION['full_name']))?>';
const STORE_KEY = 'cms_chat_<?=(int)$_SESSION['bank_id']?>';
let messages = [];

function saveMessages(){ sessionStorage.setItem(STORE_KEY, JSON.stringify(messages.slice(-80))); }
function loadMessages(){ messages = JSON.parse(sessionStorage.getItem(STORE_KEY)||'[]'); }

function renderChat(){
  const box = document.getElementById('chat-box');
  const empty = document.getElementById('empty-msg');
  if(!messages.length){ empty.style.display='block'; return; }
  empty.style.display='none';
  box.innerHTML='';
  messages.forEach(m=>{
    const mine = m.who===ME;
    const div=document.createElement('div');
    div.className='chat-msg'+(mine?' mine':'');
    div.innerHTML=`<div class="who">${mine?'You':m.who}</div>
                   <div class="txt">${escHtml(m.text)}</div>
                   <div class="ts">${m.ts}</div>`;
    box.appendChild(div);
  });
  box.scrollTop=box.scrollHeight;
}

function escHtml(s){ const d=document.createElement('div');d.textContent=s;return d.innerHTML; }

function sendMsg(){
  const inp=document.getElementById('msg-input');
  const text=inp.value.trim();
  if(!text) return;
  const msg={who:ME,text,ts:new Date().toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'})};
  messages.push(msg);
  saveMessages();
  renderChat();
  inp.value='';
  // Broadcast via storage event (other tabs)
  localStorage.setItem(STORE_KEY+'_broadcast', JSON.stringify({...msg,t:Date.now()}));
}

document.getElementById('send-btn').onclick=sendMsg;
document.getElementById('msg-input').addEventListener('keydown',e=>{
  if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMsg();}
});

// Listen for messages from other tabs/windows
window.addEventListener('storage',e=>{
  if(e.key===STORE_KEY+'_broadcast'&&e.newValue){
    const m=JSON.parse(e.newValue);
    if(m.who!==ME){
      messages.push(m);
      saveMessages();
      renderChat();
    }
  }
});

loadMessages();
renderChat();
</script>
<?php
$body_html=ob_get_clean();
require 'includes/layout.php';
echo $body_html;
echo '</body></html>';
