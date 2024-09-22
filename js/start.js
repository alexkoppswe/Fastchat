document.addEventListener('DOMContentLoaded', function () {
  const chatCodeInput = document.getElementById('chatCode');
  const starterForm = document.getElementById('start-form');
  const fillBtn = document.getElementById('fillForm');
  const chatCodeContainer = document.getElementById('chatCodeID');
  const chatCode = chatCodeContainer.dataset.chatCode;

  // Fill chatCode on click
  fillBtn.addEventListener('click', fill);

  function fill(event) {
    const chatCodeInputField = document.getElementById('chatCode');
    chatCodeInputField.value = chatCode.toUpperCase();
    event.preventDefault();
  }

  // Send start form on Enter press
  starterForm.addEventListener('keypress', function(event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      //history.pushState("", "", url);
      starterForm.requestSubmit();
    }
  });

  // Custom validation
  chatCodeInput.addEventListener('invalid', function () {
    if (chatCodeInput.validity.valueMissing) {
      chatCodeInput.setCustomValidity('This field cannot be left blank.');
    } else if (chatCodeInput.validity.tooShort || chatCodeInput.validity.tooLong) {
      chatCodeInput.setCustomValidity('Chatroom ID must be exactly 13 characters.');
    } else if (chatCodeInput.validity.patternMismatch) {
      chatCodeInput.setCustomValidity('Only letters and numbers. Letters are case sensitive.');
    } else if (chatCodeInput.validity.badInput) {
      chatCodeInput.setCustomValidity('Only letters and numbers. Must be 13 characters.');
    } else {
      chatCodeInput.setCustomValidity('');
    }
  });

  // Reset custom message on input change
  chatCodeInput.addEventListener('input', function () {
    chatCodeInput.setCustomValidity('');
  });
});