# Petanco to Flamingo - 仕様書

## 概要

このプラグインは、Petanco のシステムからの API リクエストを受け取り、応募データを Flamingo に保存します。また、レート制限、認証機能も提供します。

## 利用条件

- 最新の WordPress
- Flamingo(バージョン 2.5)
- https(SSL)

## 主要機能

1. **REST API エンドポイント**

   - エンドポイント: `/wp-json/petanco-api/v1/submit`
   - メソッド: POST
   - 認証: カスタムヘッダー（X-Petanco-API-Key）

2. **データ保存**

   - 受信したデータを Flamingo の受信メッセージとして保存

3. **レート制限**

   - 1 時間あたりの最大リクエスト数を設定可能

4. **管理画面設定**
   - Flamingo 管理画面内に「Petanco 連携設定」ページを追加

## 設定項目

1. API エンドポイントの有効化
2. シークレットキー
3. レート制限（1 時間あたりの最大リクエスト数）

## API 仕様

### リクエスト

- ヘッダー:

  - `X-Petanco-API-Key`: シークレットキー
  - `Content-Type: application/json`

- ボディ（JSON）:
  ```json
  {
    "subject": "特典名",
    "name": "応募者名",
    "email": "メールアドレス",
    "tel": "電話番号",
    "zip": "郵便番号",
    "pref": "都道府県",
    "city": "市区町村",
    "address1": "住所1",
    "address2": "住所2",
    "campaign_id": "キャンペーンID",
    "benefit_id": "特典ID",
    "player_id": "プレイヤーID"
  }
  ```

### レスポンス

- 成功時:

  - ステータスコード: 200
  - ボディ: `{"message": "送信が正常に保存されました。"}`

- エラー時:
  - ステータスコード: 400, 403, 429, または 500
  - ボディ: `{"code": "エラーコード", "message": "エラーメッセージ", "data": {"status": ステータスコード}}`

## 注意事項

- このプラグインを使用するには、Flamingo(バージョン 2.5 以降) プラグインがインストールされ、有効化されている必要があります。
- 実際の使用時は、`your-wordpress-site.com`を実際の WordPress サイトの URL に、`your-secret-key-here`を設定した実際のシークレットキーに置き換えてください。

## インストール方法

1. WordPress の管理画面「プラグイン」でプラグイン(zip ファイル)をアップロードします。
2. WordPress 管理画面でプラグインを有効化します。
3. Flamingo 管理画面内の「Petanco 連携設定」ページで必要な設定を行います。
