<?php
declare(strict_types=1);
$pageTitle = 'AI Chat';
$dashDir   = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';
$sessionId = bin2hex(random_bytes(8));
?>

<div class="ai-chat-container">
  <div class="ai-messages" id="ai-messages">
    <div class="ai-message assistant">
      <span class="ai-avatar">🤖</span>
      <div class="ai-bubble">Hello! I'm your KronosCMS AI assistant. Ask me anything about managing your website — content, settings, products, or general help.</div>
    </div>
  </div>

  <form class="ai-input-form" id="ai-form">
    <input type="text" id="ai-input" class="ai-input" placeholder="Ask something…" autocomplete="off" required>
    <button type="submit" class="btn btn-primary">Send</button>
  </form>
</div>

<script>
(function() {
  const form      = document.getElementById('ai-form');
  const input     = document.getElementById('ai-input');
  const messages  = document.getElementById('ai-messages');
  const sessionId = <?= json_encode($sessionId) ?>;

  function addMessage(role, text) {
    const div = document.createElement('div');
    div.className = 'ai-message ' + role;
    const avatar = document.createElement('span');
    avatar.className = 'ai-avatar';
    avatar.textContent = role === 'user' ? '👤' : '🤖';

    const bubble = document.createElement('div');
    bubble.className = 'ai-bubble';
    bubble.textContent = text;

    div.appendChild(avatar);
    div.appendChild(bubble);
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
  }

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    addMessage('user', msg);

    const thinking = document.createElement('div');
    thinking.className = 'ai-message assistant thinking';
    thinking.innerHTML = '<span class="ai-avatar">🤖</span><div class="ai-bubble">⋯</div>';
    messages.appendChild(thinking);
    messages.scrollTop = messages.scrollHeight;

    try {
      const res  = await window.KronosDash.api('/ai/chat', 'POST', { message: msg, session_id: sessionId });
      thinking.remove();
      addMessage('assistant', (res && res.message) || 'No response.');
    } catch(err) {
      thinking.remove();
      addMessage('assistant', 'Error: ' + err.message);
    }
  });
})();
</script>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
