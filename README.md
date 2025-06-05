# 記事リライトツール（Google Sheets × OpenAI API）

## プロジェクト概要

Google Sheetsから記事データを取得し、OpenAI APIを活用してSEO観点で自動リライト・改善を行うWebアプリケーションです。

- **主な機能**
  - Google Sheetsから記事データを取得
  - 各記事のタイトル・ディスクリプション・本文を表示
  - OpenAI APIで「改善点分析」「自動リライト」
  - フィルタ・ソート・リライト回数管理

---

## ディレクトリ構成・主要ファイル

```
.
├── index.php               # メイン画面・記事一覧/フィルタ/リライト起動
├── detail.php              # 記事詳細ページ
├── process.php             # 一括処理用
├── process_single.php      # 単体記事処理用
├── functions.php           # 主要ロジック（API, データ処理, エラーハンドリング等）
├── config.php              # 環境変数・設定
├── includes/
│   ├── api/                # API関連分割用ディレクトリ（今後のリファクタリング用）
│   ├── google/             # Google Sheets関連分割用
│   └── utils/              # 汎用関数分割用
```

---

## システム設計・主な処理フロー

1. **Google Sheetsから記事データ取得**
   - APIキー/スプレッドシートIDは環境変数で管理
   - 取得データは配列として保持

2. **一覧表示・フィルタ機能**
   - キーワード、リライト回数、表示回数などで絞込
   - ソートも可能

3. **記事ごとにOpenAI APIで分析・リライト**
   - `functions.php`の`analyzeArticleIssues`で改善点を生成
   - `improveArticle`でリライト案を生成
   - 進捗・エラーは画面/ログ両方に表示

4. **リライト回数管理**
   - 各記事ごとにリライト実施回数をカウント
   - Google Sheets側にも反映可能

---

## ここまでのエラー解決・改善経緯

### 発生していた問題
- OpenAI API呼び出し時に `json_encode失敗: Malformed UTF-8 characters, possibly incorrectly encoded` エラーが頻発
- タイトル・本文などが一見正常でも、APIリクエスト時にHTTP 400エラー

### 対応策
1. **UTF-8正規化・不可視バイト除去**
   - `mb_convert_encoding`/`iconv`/正規表現で不可視バイトや制御文字を除去
2. **1文字ずつUTF-8チェック**
   - 不正な文字は「?」に置換
3. **json_encode失敗時の詳細ログ・画面返却**
   - どのフィールドが壊れているか、データ内容も画面出力
4. **json_encodeに `JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE` を追加**
   - これにより、ほぼ全てのUTF-8エラーが解消

### 結果
- どんなデータでもAPIリクエストが通るようになり、安定運用可能に
- エラー時も原因特定が容易になった

---

## 今後のリファクタリング方針
- `functions.php`の肥大化部分をAPI/Google/utils等に分割
- 共通処理やエラーハンドリングをモジュール化
- コード可読性・保守性向上

---

## 補足・運用Tips
- サーバーエラーログは `/var/log/apache2/error.log` や `php_error.log` を参照
- OpenAI/Google APIキーは漏洩注意
- PHPのバージョン依存バグにも注意（推奨: 7.4以降）

---

## 開発・運用履歴
- 2025/06: UTF-8エラー根絶・安定運用化
- 2025/06: エラー詳細ダンプ・デバッグ機能追加
- 2025/06: コード分割リファクタリング計画開始

---

## ライセンス・著作権
- 本ツールの著作権は開発者に帰属
- 商用利用・再配布は要相談
