document.addEventListener('DOMContentLoaded', function () {
    // ハンバーガーメニュー
    const toggleButton = document.getElementById('menu-toggle');
    const sideMenu = document.getElementById('side-menu');

    toggleButton.addEventListener('click', function () {
        sideMenu.classList.toggle('open');
    });

    document.addEventListener('click', function (event) {
        if (!sideMenu.contains(event.target) && !toggleButton.contains(event.target)) {
            sideMenu.classList.remove('open');
        }
    });

    // CSVファイル選択ラベル変更
    const fileInput = document.getElementById('csv-file');
    const fileLabel = document.querySelector('.custom-file-label');

    if (fileInput && fileLabel) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files.length > 0) {
                fileLabel.textContent = fileInput.files[0].name;
            } else {
                fileLabel.textContent = 'CSVファイルを選択';
            }
        });
    }
});
