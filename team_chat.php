<?php
require 'includes/config.php';
require_role(['bank_staff','bank_admin']);

$page_title='Team Chat';
ob_start();
?>
<div class="page-wrap" style="max-width:760px">
  <div class="page-header">
    <h1>Team Chat</h1>
    <p>Real-time communication - messages auto-delete after 2 hours.</p>
  </div>

  <div class="card">
    <div id="chat-box" class="chat-box">
      <div style="text-align:center;color:var(--muted);font-size:13px;padding:20px 0" id="empty-msg">
        Loading messages...
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:14px">
      <input id="msg-input" class="form-control" placeholder="Type a message... (Enter to send)" style="flex:1">
      <button id="send-btn" class="btn btn-primary">Send</button>
    </div>
    <div class="text-xs text-muted" style="margin-top:8px">
      Messages are deleted automatically after 2 hours.
    </div>
  </div>
</div>

<input type="hidden" id="chat-csrf" name="csrf" value="<?=csrf_token()?>">

<script>
const API = 'includes/team_chat_api.php';
const MY_USER_ID = <?=json_encode((int)$_SESSION['user_id'])?>;
const POLL_MS = 2000;

let messages = [];
let pollInterval = null;
let isSending = false;

function escHtml(s){
  const d = document.createElement('div');
  d.textContent = s ?? '';
  return d.innerHTML;
}

function formatTime(ts){
  const d = new Date(String(ts || '').replace(' ','T'));
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
}

function renderChat(){
  const box = document.getElementById('chat-box');
  const shouldStickBottom = (box.scrollHeight - box.scrollTop - box.clientHeight) < 80;
  box.innerHTML = '';

  if(!messages.length){
    box.innerHTML = '<div id="empty-msg" style="text-align:center;color:var(--muted);font-size:13px;padding:20px 0">No messages yet. Start the conversation!</div>';
    return;
  }

  messages.forEach(m => {
    const mine = Number(m.user_id) === MY_USER_ID;
    const div = document.createElement('div');
    div.className = 'chat-msg' + (mine ? ' mine' : '');
    div.innerHTML = `<div class="who">${mine ? 'You' : escHtml(m.full_name)}</div>
                     <div class="txt">${escHtml(m.message)}</div>
                     <div class="ts">${formatTime(m.created_at)}</div>`;
    box.appendChild(div);
  });

  if (shouldStickBottom || isSending) {
    box.scrollTop = box.scrollHeight;
  }
}

function showLoadError(){
  const box = document.getElementById('chat-box');
  box.innerHTML = '<div id="empty-msg" style="text-align:center;color:var(--muted);font-size:13px;padding:20px 0">Failed to load messages.</div>';
}

function fetchMessages(){
  return fetch(API + '?_=' + Date.now(), {
    method:'GET',
    cache:'no-store',
    headers:{'Accept':'application/json'}
  })
    .then(r => {
      if(!r.ok) throw new Error('Failed to fetch');
      return r.json();
    })
    .then(data => {
      if(!Array.isArray(data)) throw new Error('Invalid response');
      messages = data;
      renderChat();
    })
    .catch(() => {
      showLoadError();
    });
}

function sendMsg(){
  if(isSending) return;

  const inp = document.getElementById('msg-input');
  const btn = document.getElementById('send-btn');
  const csrf = document.getElementById('chat-csrf');
  const text = inp.value.trim();

  if(!text || !csrf) return;

  isSending = true;
  btn.disabled = true;
  inp.disabled = true;

  fetch(API, {
    method:'POST',
    cache:'no-store',
    headers:{
      'Content-Type':'application/x-www-form-urlencoded',
      'Accept':'application/json'
    },
    body:'csrf=' + encodeURIComponent(csrf.value) + '&message=' + encodeURIComponent(text)
  })
    .then(r => {
      if(!r.ok) throw new Error('Failed to send');
      return r.json();
    })
    .then(data => {
      if(!Array.isArray(data)) throw new Error('Invalid response');
      messages = data;
      inp.value = '';
      renderChat();
    })
    .catch(() => {
      alert('Message could not be sent. Please try again.');
    })
    .finally(() => {
      isSending = false;
      btn.disabled = false;
      inp.disabled = false;
      inp.focus();
      fetchMessages();
    });
}

function stopPolling(){
  if(pollInterval){
    clearInterval(pollInterval);
    pollInterval = null;
  }
}

function startPolling(){
  stopPolling();
  pollInterval = setInterval(fetchMessages, POLL_MS);
}

document.getElementById('send-btn').addEventListener('click', sendMsg);
document.getElementById('msg-input').addEventListener('keydown', e => {
  if(e.key === 'Enter' && !e.shiftKey){
    e.preventDefault();
    sendMsg();
  }
});

document.addEventListener('visibilitychange', () => {
  if(document.visibilityState === 'visible'){
    fetchMessages();
    startPolling();
  } else {
    stopPolling();
  }
});

window.addEventListener('beforeunload', stopPolling);

fetchMessages().finally(startPolling);
</script>
<?php
$body_html=ob_get_clean();
require 'includes/layout.php';
echo $body_html;
echo '</body></html>';
