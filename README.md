# Petanco to Flamingo - 仕様書

## 概要

このプラグインは、Petanco のシステムからの API リクエストを受け取り、応募データを Flamingo に保存します。API キー認証、レート制限、CORS 制御、多言語ラベル対応を提供します。

## 利用条件

- 最新の WordPress（6.8 以降推奨）
- Flamingo（バージョン 2.5 以降）
- HTTPS（SSL 必須）

## 主要機能

1. **REST API エンドポイント**

   - エンドポイント: `/wp-json/petanco-api/v1/submit`
   - メソッド: POST
   - 認証: カスタムヘッダー（`X-Petanco-API-Key`）

2. **データ保存**

   - 受信したデータを Flamingo の受信メッセージとして保存

3. **レート制限**

   - 1 時間あたりの最大リクエスト数を設定可能（デフォルト: 300）

4. **CORS 制御**

   - 許可オリジン: `https://petanco.io` および `https://petanco.net`

5. **多言語ラベル対応**

   - 保存される本文ラベルが `language` パラメータに応じて切り替わる（`ja` / `en` / `ko`）

6. **バージョンチェック**

   - GitHub Releases API から最新バージョンを取得し、管理画面で更新を通知

7. **管理画面設定**
   - Flamingo 管理画面内に「Petanco 連携設定」ページを追加

## 設定項目

1. API エンドポイントの有効化
2. シークレットキー（AES-256-CBC で暗号化して保存）
3. レート制限（1 時間あたりの最大リクエスト数）

## API 仕様

### リクエスト

- ヘッダー:

  - `X-Petanco-API-Key`: シークレットキー（必須）
  - `Content-Type: application/json`（必須）
  - `User-Agent`: 任意の値（必須。空の場合 400 エラー）

- ボディ（JSON）:

  | フィールド    | 型     | 必須 | 説明                                       |
  | ------------- | ------ | ---- | ------------------------------------------ |
  | `subject`     | string | ○    | 特典名                                     |
  | `name`        | string | ○    | 応募者名                                   |
  | `email`       | string | ○    | メールアドレス（形式チェックあり）         |
  | `tel`         | string | ○    | 電話番号                                   |
  | `pref`        | string | ○    | 都道府県                                   |
  | `address1`    | string | ○    | 住所 1                                     |
  | `campaign_id` | string | ○    | キャンペーン ID                            |
  | `benefit_id`  | string | ○    | 特典 ID                                    |
  | `player_id`   | string | ○    | プレイヤー ID                              |
  | `zip`         | string |      | 郵便番号                                   |
  | `city`        | string |      | 市区町村                                   |
  | `address2`    | string |      | 住所 2                                     |
  | `country`     | string |      | 国                                         |
  | `language`    | string |      | ラベル言語（`ja` / `en` / `ko`、デフォルト: `ja`） |

  リクエスト例:

  ```json
  {
    "subject": "特典名",
    "name": "応募者名",
    "email": "user@example.com",
    "tel": "090-1234-5678",
    "zip": "100-0001",
    "pref": "東京都",
    "city": "千代田区",
    "address1": "千代田1-1",
    "address2": "○○ビル 3F",
    "country": "日本",
    "language": "ja",
    "campaign_id": "campaign_abc123",
    "benefit_id": "benefit_xyz789",
    "player_id": "player_001"
  }
  ```

### レスポンス

- 成功時（200）:

  ```json
  {
    "message": "応募が正常に完了しました。",
    "callout": "2024-09-26 12:34:56"
  }
  ```

- バリデーションエラー時（400）:

  ```json
  {
    "code": "validation_failed",
    "message": "入力データが無効です。",
    "data": {
      "status": 400,
      "errors": {
        "name": "nameは必須です。",
        "email": "有効なメールアドレスを入力してください。"
      },
      "callout": "2024-09-26 12:34:56"
    }
  }
  ```

- User-Agent 未指定時（400）:

  ```json
  {
    "code": "invalid_user_agent",
    "message": "有効なUser-Agentが必要です。",
    "data": { "status": 400 }
  }
  ```

- 認証エラー時（403）:

  ```json
  {
    "code": "rest_forbidden",
    "message": "アクセスが拒否されました。",
    "data": { "status": 403 }
  }
  ```

- レート制限超過時（429）:

  ```json
  {
    "code": "rate_limit_exceeded",
    "message": "レート制限を超えました。しばらくしてからもう一度お試しください。",
    "data": { "status": 429 }
  }
  ```

- サーバーエラー時（500）:

  ```json
  {
    "code": "submission_failed",
    "message": "送信の保存に失敗しました。",
    "data": {
      "status": 500,
      "callout": "2024-09-26 12:34:56"
    }
  }
  ```

## 注意事項

- このプラグインを使用するには、Flamingo（バージョン 2.5 以降）プラグインがインストールされ、有効化されている必要があります。
- SSL 環境でのみ動作します。非 SSL 環境ではプラグインが自動的に無効化されます。
- CORS により `https://petanco.io` 以外のオリジンからのリクエストは拒否されます。

## インストール方法

1. WordPress の管理画面「プラグイン」でプラグイン（zip ファイル）をアップロードします。
2. WordPress 管理画面でプラグインを有効化します。
3. Flamingo 管理画面内の「Petanco 連携設定」ページで必要な設定を行います。

## プラグインダウンロード URL

- `https://github.com/GOWASJP/petanco-to-flamingo/releases/latest`
