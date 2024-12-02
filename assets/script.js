
document.getElementById('num_instances').addEventListener('input', function () {
    const numInstances = parseInt(this.value);
    const titlesContainer = document.getElementById('titles-container');
    titlesContainer.innerHTML = '';
    if (numInstances > 0) {
        for (let i = 0; i < numInstances; i++) {
            const div = document.createElement('div');
            div.className = 'input-container';
            div.innerHTML = `
                <label for="title_${i}">Title for Instance ${i + 1}</label>
                <input type="text" id="title_${i}" name="titles[]" required>
            `;
            titlesContainer.appendChild(div);
        }
    }
});

const togglePassword = document.getElementById('togglePassword');
const passwordField = document.getElementById('mysql_pass');
togglePassword.addEventListener('click', () => {
    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordField.setAttribute('type', type);
    togglePassword.innerHTML = type === 'password' ? 'visibility' : 'visibility_off';
});

    function setConfigInstance(instanceDir, siteTitle) {
        document.getElementById('configInstanceDir').value = instanceDir;
        document.getElementById('siteTitle').value = siteTitle;
    }
        function loadLogs() {
            fetch('panel.php?action=get_logs')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('logsContainer').textContent = data;
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }