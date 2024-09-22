// HTMX Configuration
htmx.on('htmx:config', (config) => {
  config.ariaLive = 'assertive';
  config.ariaCurrent = 'page';
  config.withCredentials = true;
  config.referrerPolicy = 'same-origin';
  config.history = true;
  config.historyCacheSize = 90;
  
  config.refreshOnHistoryMiss = true;
});

document.addEventListener('DOMContentLoaded', () => {
  const debugging = 1;
  const debuggingMessages = 0;
  const controllerAbort = new AbortController();

  const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  const messageInput = document.getElementById('message-input');
  const messageForm = document.getElementById('message-form');
  const emojiPicker = document.getElementById('emoji-picker');
  const mojiBar = document.getElementById('moji-bar');
  const emojiList = document.getElementById('emoji-list');
  const emojiCategories = document.getElementById('emoji-categories');
  const submitBtn = document.getElementById('submit-btn');
  const copyIcon = document.getElementById('copyIcon');
  const titleElement = document.querySelector('#chat-titlebar-room .id-text');
  const titleText = titleElement.textContent;
  const xButton = document.getElementById('xbutton');

  let submitTimeout = 0;
  let submitInterval = null;

  // History
  window.onload = () => {
    setHistory();
  };

  // History request
  function setHistory(url = '') {
    const state = new Date().toISOString();
    if (!url || !/^https?:\/\//.test(url)) {
      url = 'chatroom.php';
    }  
    history.pushState(state, null, url);
  }

  // Form Handling Submit
  const submitForm = () => {
    if (submitTimeout > 0) return;

    const messageInputValue = messageInput.value.trim();
    if (!messageInputValue || messageInputValue === '') return;
    
    if (submitTimeout <= 1) {
      clearInterval(submitInterval);
    }

    messageForm.requestSubmit();
    submitTimeout = 1.5; // 1.5 seconds delay
    submitBtn.disabled = true;
    submitBtn.style.filter = 'grayscale(100%)';
    resetInactivityTimer();

    submitInterval = setInterval(() => {
      submitTimeout -= 0.1;
      submitBtn.value = `Send ${submitTimeout.toFixed(1)}`;
      if (submitTimeout <= 0) {
        clearInterval(submitInterval);
        submitInterval = null;
        submitBtn.value = 'Send';
        submitBtn.disabled = false;
        submitBtn.style.filter = 'grayscale(0%)';
      }
    }, 100);
  };

  const handleOutgoingMessages = (event) => {
    if (event.detail.target.id === 'message-form') {
      messageInput.value = '';
      messageInput.style.height = 'initial';
    };
  };

  let errorCount = 0;
  const handleErrors = (event) => {
    document.getElementById('user-list-ul').textContent = '';
    document.getElementById('chat-window').textContent = 'Something went wrong. Please update or try again later.';

    console.error(`Error: ${event.message}`);

    errorCount++;
    if (errorCount > 3 || event.message === 'undefined') {
      htmxStopPolling();
      window.location.reload(true);
      return;
    }

    if(debugging === 1) {
      document.getElementById('chat-window').textContent = `Error: ${event.message}`;
    }
  };

  // User AFK
  let inactivityTimeout;
  let afkTimeout;
  let leaveTimeout;
  let pollingEnabled = true;
  
  const updateUserStatus = async (status) => {
    const trimmedStatus = status.trim();
    if (trimmedStatus === '' || /[^a-zA-Z]/.test(trimmedStatus)) return;
    if (!csrfToken || csrfToken === '') return;

    try {
      const url = `chat.php?action=updateStatus&userStatus=${trimmedStatus}`;
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
          'X-Htmx-Request': 'true',
          'X-CSRF-Token': csrfToken,
        }
      });

      if (!response.ok) {
        console.error(`Error updating user status: ${response.statusText}`);
      }
    } catch (error) {
      if (error.name === 'TypeError') {
        console.error('Network error occurred:', error);
      } else {
        console.error(`Error updating user status: ${error.message}`);
      }
    }
  };

  function startInactivityTimer() {
    clearTimeout(inactivityTimeout);
    clearTimeout(afkTimeout);
    clearTimeout(leaveTimeout);

    inactivityTimeout = setTimeout(() => {
      updateUserStatus('away');
      if (pollingEnabled) {
        htmxStopPolling();
      }
    }, 300000); // 5min

    afkTimeout = setTimeout(() => {
      updateUserStatus('afk');
    }, 1800000); // 30min

    leaveTimeout = setTimeout(() => {
      htmxStopPolling();
      endChat();
    }, 3600000); // 60min

    if (debugging && debuggingMessages) {
      console.log('Starting inactivity timer');
    }
  }
  
  function resetInactivityTimer() {
    clearTimeout(inactivityTimeout);
    clearTimeout(afkTimeout);
    clearTimeout(leaveTimeout);
    
    updateUserStatus('online');
    
    if (!pollingEnabled) {
      htmxStartPolling();
    }

    if(debugging && debuggingMessages) {
      console.log('Resetting inactivity timer');
    }
  }

  document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'hidden') {
      startInactivityTimer();
    }
    if(document.visibilityState === 'visible') {
      resetInactivityTimer();
    }
  });

  document.addEventListener('focus', resetInactivityTimer);
  document.addEventListener('blur', startInactivityTimer);

  function htmxStopPolling() {
    if (pollingEnabled) {
      htmx.trigger('#user-list', 'htmx:abort');
      htmx.trigger('#chat-window', 'htmx:abort');
      pollingEnabled = false;
      controllerAbort.abort();

      if(debugging && debuggingMessages) {
        console.log('Stopping polling');
      }
    }
  }
  
  function htmxStartPolling() {
    if (!pollingEnabled) {
      htmx.trigger(document.body, 'htmx:poll');
      pollingEnabled = true;

      if(debugging && debuggingMessages) {
        console.log('Starting polling');
      }
    }
  }

  const endChat = function () {
    if (!csrfToken?.trim()) return;
  
    try {
      fetch('chat.php?action=end', {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-Htmx-Request': 'true',
          'X-CSRF-Token': csrfToken,
        }
      }).then(() => {
        window.location.href = 'index.php';
      });      
    } catch (error) {
      console.error(`Error ending chat: ${error.message}`);
    }
  };

  // HTMX Event Listeners
  document.addEventListener('htmx:send', handleOutgoingMessages);
  document.addEventListener('htmx:error', handleErrors);

  document.addEventListener('htmx:beforesend', (event) => {
    if (event.detail.target.id === 'message-form') {
      const messageInputValue = messageInput.value.trim();
      if (messageInputValue === '') return;
    }
    if (event.detail.target.id === 'xbutton') {
      htmxStopPolling();
    }
  });

  document.addEventListener('htmx:afterRequest', (event) => {
    if(event.detail.error) {
      handleErrors(event);
      return;
    } else {
      event.detail.target.scrollTop = event.detail.target.scrollHeight;
    }
  });

  document.addEventListener('htmx:sendAbort', (event) => {
    if(event.detail.error) {
      handleErrors(event);
      return;
    } else {
      event.detail.target.scrollTop = event.detail.target.scrollHeight;
    }
  });

  // Form Submission
  submitBtn.addEventListener('click', submitForm);

  messageInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      submitForm();
    }
  });

  // Custom validation
  messageInput.addEventListener('invalid', function () {
    if (messageInput.validity.valueMissing) {
      messageInput.setCustomValidity('This field cannot be left blank.');
    } else {
      messageInput.setCustomValidity('');
    }
  });

  messageInput.addEventListener('input', function () {
    messageInput.setCustomValidity('');
  });

  // Textarea height
  messageInput.addEventListener('input', () => {
    requestAnimationFrame(adjustTextareaHeight);
  });

  messageInput.addEventListener('resize', () => {
    messageInput.scrollTop = messageInput.scrollHeight;
  });

  // Adjust form height
  const adjustTextareaHeight = () => {
    const lineHeight = getLineHeight(messageInput);
    const lines = getLines(messageInput);
    const height = lines * lineHeight;
    const maxHeight = 350; // Max height in pixels
    messageInput.style.height = `${Math.min(height, maxHeight)}px`;
  };

  const getLineHeight = (textarea) => {
    const styles = getComputedStyle(textarea);
    const fontSize = parseFloat(styles.fontSize);
    const lineHeight = parseFloat(styles.lineHeight) || fontSize * 1.2;
    return lineHeight;
  };

  const getLines = (textarea) => {
    const value = textarea.value;
    const lines = value.split(/\r\n|\r|\n/).length;
    return lines;
  };

  // Copy to Clipboard
  copyIcon.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(titleText);
      copyIcon.textContent = '✔️';
      copyIcon.dataset.title = "ID copied to clipboard";
      copyIcon.classList.add('success');
      setTimeout(() => {
        copyIcon.textContent = '📋';
        copyIcon.dataset.title = "Copy to Clipboard";
        copyIcon.classList.remove('success');
      }, 2000);
    } catch (err) {
      copyIcon.textContent = '❌';
      copyIcon.dataset.title = "Failed to copy ID";
      copyIcon.classList.add('error');
      console.error(err.message);
      setTimeout(() => {
        copyIcon.textContent = '📋';
        copyIcon.classList.remove('error');
      }, 2000);
    }
  });

  // Emojis
  const emojis = {
    faces: [
      '😊', '😁', '😆', '😉', '😄', '🙃', '😅', '😃', '😂', '🤣',
      '🙂', '😀', '😇', '🥺', '😳', '😢', '😩', '😱', '😞', '☹️',
      '😥', '😫', '😲', '😕', '😓', '😟', '😖', '😮', '🙁', '😰',
      '😯', '😨', '😧', '😦', '😭', '😣', '😘', '🥰', '😍', '😊',
      '🤩', '😚', '😙', '😗', '🙄', '😏', '😬', '😐', '😒', '😑',
      '🤨', '😶', '🤐', '🤗', '🤔', '🤭', '🤫', '🥳', '🤠', '😎',
      '🧐', '🤓', '😔', '🤤', '😌', '😪', '😴', '😜', '🤪', '😝',
      '😛', '🤑', '😋', '🥵', '🥴', '🤮', '🤢', '🤯', '😷', '🥶',
      '😵', '🤧', '🤕', '🤒', '😤', '😠', '🤬', '😡', '👿', '😈'
    ],
    body: [
      '👀', '👅', '👄', '👍', '🖐️', '🙌', '🙏', '👌', '👊',
      '👎', '👏', '👐', '🤲', '🤝', '✌️', '🤘', '🤙', '🤟',
      '🤞', '💪', '🖕', '☝️', '👆', '👈', '👇', '👉', '👋',
      '✊', '💅', '👶', '🤷', '🙋', '💁', '🙅', '🙇', '🤦',
      '🚶', '🏃', '💃', '☠️', '💀', '🐵', '🙉', '🙊', '🙈',
      '👹', '👻', '👽', '👺', '🤖', '👾', '🤡', '💩',
      '😹', '😻', '😸', '😺', '😽', '😾', '😿', '🙀', '😼'
    ],
    objects: [
      '📱', '💻', '📺', '🎤', '💣', '💡', '📰', '🎨',
      '📸', '📹', '🎥', '📺', '📻', '🎵', '🎶',
      '💸', '💴', '💵', '💰', '💲', '🚀', '👑',
      '🎯', '🎂', '🎁', '🎈', '🎊', '🎉', '🌹', '🥀',
      '🍀', '🍆', '🍑', '☕', '🥂', 
    ],
    other: [
      '❤️', '💔', '💕', '💞', '💟', '💬', '💭', '💤',
      '💢', '💥', '💯', '', '✅', '💦', '💧', '💨', '🔥',
      '🌞', '☀️', '🌟', '🌈', '⚡', '⭐', '✨', '💫',
    ]
  };
  
  let currentCategory = 'faces';
  
  const toggleEmojiPicker = () => {
    emojiPicker.classList.toggle('hidden');
  };

  mojiBar.addEventListener('click', toggleEmojiPicker);
  document.addEventListener('click', (e) => {
    if (!emojiPicker.contains(e.target) &&!mojiBar.contains(e.target)) {
      emojiPicker.classList.add('hidden');
    }
  });
  
  const updateEmojiList = () => {
    emojiList.innerHTML = '';
    emojis[currentCategory].forEach((emoji) => {
      const span = document.createElement('span');
      span.className = 'emoji';
      span.textContent = emoji;
      span.addEventListener('click', (e) => {
        messageInput.value += e.target.textContent;
        messageInput.focus();
        toggleEmojiPicker();
      });
      emojiList.appendChild(span);
    });
  };

  if(emojiCategories) {
    emojiCategories.addEventListener('click', (e) => {
      if (e.target.classList.contains('emoji-category')) {
        const previouslySelected = document.querySelector('.emoji-category.selected');
        if (previouslySelected) {
          previouslySelected.classList.remove('selected');
        }
        currentCategory = e.target.dataset.category;
        e.target.classList.add('selected');
        updateEmojiList();
      }
    });
  }
  updateEmojiList();
  
});