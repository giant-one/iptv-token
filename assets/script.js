document.addEventListener('DOMContentLoaded', function() {
    // 确认删除操作
    const confirmDeletes = document.querySelectorAll('.confirm-delete');
    if (confirmDeletes) {
        confirmDeletes.forEach(function(element) {
            element.addEventListener('click', function(e) {
                if (!confirm('确定要删除吗？此操作无法撤销')) {
                    e.preventDefault();
                }
            });
        });
    }

    // 复制链接到剪贴板
    const copyLinks = document.querySelectorAll('.copy-link');
    if (copyLinks) {
        copyLinks.forEach(function(element) {
            element.addEventListener('click', function(e) {
                e.preventDefault();
                const text = this.getAttribute('data-link');
                
                // 创建临时输入框
                const input = document.createElement('input');
                input.value = text;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                
                // 显示提示
                alert('链接已复制到剪贴板！');
            });
        });
    }

    // 自动消失的提示框
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length) {
        setTimeout(function() {
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 3000);
    }
});
