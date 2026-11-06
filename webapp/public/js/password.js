/* Pulsante "occhio": mostra o nasconde la password del campo accanto. */
document.querySelectorAll('[data-mostra-password]').forEach((b) => {
  b.addEventListener('click', () => {
    const campo = b.parentElement.querySelector('input');
    const visibile = campo.type === 'password';
    campo.type = visibile ? 'text' : 'password';
    b.setAttribute('aria-pressed', String(visibile));
    b.setAttribute('aria-label', visibile ? 'Nascondi password' : 'Mostra password');
    b.title = b.getAttribute('aria-label');
    b.classList.toggle('visibile', visibile);
    campo.focus();
  });
});
