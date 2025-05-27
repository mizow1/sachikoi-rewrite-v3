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

    // URLパラメータを取得
    const urlParams = new URLSearchParams(window.location.search);
    const sortParam = urlParams.get('sort');
    const keywordParam = urlParams.get('keyword');

    // キーワード検索の処理
    const keywordInput = document.getElementById('keyword');
    const searchBtn = document.getElementById('searchBtn');
    
    if (keywordInput && searchBtn) {
        // キーワードパラメータがあれば入力欄に反映
        if (keywordParam) {
            keywordInput.value = keywordParam;
        }
        
        // 検索ボタンクリック時の処理
        searchBtn.addEventListener('click', function() {
            applyFilters();
        });
        
        // Enterキー押下時の処理
        keywordInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault(); // フォーム送信を防止
                applyFilters();
            }
        });
    }

    // 並び替え処理
    const sortBySelect = document.getElementById('sortBy');
    if (sortBySelect) {
        // ソートパラメータがあれば選択状態に反映
        if (sortParam) {
            sortBySelect.value = sortParam;
        }
        
        // 並び替え変更時の処理
        sortBySelect.addEventListener('change', function() {
            applyFilters();
        });
    }
    
    // フィルターを適用する関数
    function applyFilters() {
        const keyword = keywordInput ? keywordInput.value : '';
        const sort = sortBySelect ? sortBySelect.value : 'impressions_desc';
        
        // URLを生成してリダイレクト
        let url = 'index.php';
        let params = [];
        
        if (keyword) {
            params.push(`keyword=${encodeURIComponent(keyword)}`);
        }
        
        if (sort) {
            params.push(`sort=${encodeURIComponent(sort)}`);
        }
        
        if (params.length > 0) {
            url += '?' + params.join('&');
        }
        
        window.location.href = url;
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
