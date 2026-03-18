(() => {
    const stack = document.getElementById('toastStack');
    const flashData = document.getElementById('flashData');

    if (!stack || !flashData) {
        return;
    }

    let toast = null;

    try {
        toast = JSON.parse(flashData.textContent || 'null');
    } catch {
        toast = null;
    }

    if (!toast || typeof toast.message !== 'string' || toast.message === '') {
        return;
    }

    const element = document.createElement('div');
    element.className = `toast ${toast.type === 'success' ? 'success' : 'error'} is-entering`;
    element.setAttribute('role', 'status');
    element.textContent = toast.message;
    stack.appendChild(element);

    requestAnimationFrame(() => {
        element.classList.remove('is-entering');
    });

    window.setTimeout(() => {
        element.classList.add('is-leaving');

        window.setTimeout(() => {
            element.remove();
        }, 340);
    }, 5000);
})();
