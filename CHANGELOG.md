# Changelog

## [1.2.0] - 2026-03-09

### Added

- 多言語対応（英語 en_US / 韓国語 ko_KR）の翻訳ファイルを追加
- CORS 許可オリジンに `https://petanco.net` を追加
- `languages/` ディレクトリに POT テンプレートファイルを追加

### Changed

- テキストドメインの読み込みタイミングを `init` から `plugins_loaded`（優先度 1）に変更
- ハードコードされた日本語文字列（`wp_die()` / CORS エラー）を `__()` に変換
- CORS エラーメッセージを「オリジン不可」から「許可されていないオリジンです。」に変更

### Fixed

- プラグイン有効化時・SSL チェック時に翻訳が適用されない問題を修正

## [1.1.2] - 2025-04-24

### Fixed

- WordPress 6.8 以降の JIT 翻訳機能との互換性を修正

## [1.1.1] - 2025-02-21

### Changed

- CORS 設定を厳格化（`https://petanco.io` のみ許可）

## [1.1.0] - 2024-10-03

### Added

- バージョンチェック機能（GitHub Releases API 連携）
- 管理画面にバージョン情報とダウンロードリンクを追加
- シークレットキーの AES-256-CBC 暗号化保存

### Changed

- 多言語ボディラベル対応（ja / en / ko）

## [1.0.0] - 2024-09-20

### Added

- 初期リリース
- REST API エンドポイント (`/wp-json/petanco-api/v1/submit`)
- API キー認証（`X-Petanco-API-Key` ヘッダー）
- レート制限機能（1 時間あたりの最大リクエスト数）
- Flamingo 受信メッセージとしてデータ保存
- 管理画面設定ページ（Flamingo サブメニュー）
