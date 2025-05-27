document.addEventListener('DOMContentLoaded', function() {
    // 全選択チェックボックスの処理
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_urls[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    }

    // 並び替え処理
    const sortBySelect = document.getElementById('sortBy');
    if (sortBySelect) {
        sortBySelect.addEventListener('change', function() {
            const form = document.getElementById('rewriteForm');
            // 並び替えパラメータをURLに追加して現在のページをリロード
            const sortValue = sortBySelect.value;
            window.location.href = `index.php?sort=${sortValue}`;
        });

        // URLから並び替え設定を取得して反映
        const urlParams = new URLSearchParams(window.location.search);
        const sortParam = urlParams.get('sort');
        if (sortParam) {
            sortBySelect.value = sortParam;
        }
    }

    // 記事内容のプレビュー表示の高さ調整
    const contentPreviews = document.querySelectorAll('.content-preview');
    contentPreviews.forEach(preview => {
        // 高さが400pxを超える場合は、スクロール可能に
        if (preview.scrollHeight > 400) {
            preview.style.maxHeight = '400px';
            preview.style.overflowY = 'auto';
        }
    });

    // フォーム送信前の確認
    const rewriteForm = document.getElementById('rewriteForm');
    if (rewriteForm) {
        rewriteForm.addEventListener('submit', function(event) {
            const checkboxes = document.querySelectorAll('input[name="selected_urls[]"]:checked');
            if (checkboxes.length === 0) {
                event.preventDefault();
                alert('リライトする記事を選択してください。');
                return false;
            }

            if (checkboxes.length > 5) {
                const confirm = window.confirm(`${checkboxes.length}件の記事をリライトします。処理に時間がかかる場合があります。続行しますか？`);
                if (!confirm) {
                    event.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
    }
});
