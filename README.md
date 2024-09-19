# Petanco to Flamingo - 仕様書

## 概要

このプラグインは、Petanco のシステムからの API リクエストを受け取り、応募データを Flamingo に保存します。また、Webhook 通知、レート制限、認証機能も提供します。

**Webhook通知機能は現在、OFFにしています**

## バージョン

1.0.3

## 主要機能

1. **REST API エンドポイント**

   - エンドポイント: `/wp-json/petanco-api/v1/submit`
   - メソッド: POST
   - 認証: カスタムヘッダー（X-Petanco-API-Key）

2. **データ保存**

   - 受信したデータを Flamingo の受信メッセージとして保存

3. **Webhook 通知**

   - 保存成功時と失敗時にそれぞれ設定された URL に Webhook 通知を送信

4. **レート制限**

   - 1 時間あたりの最大リクエスト数を設定可能

5. **管理画面設定**
   - Flamingo 管理画面内に「Petanco 連携設定」ページを追加

## 設定項目

1. API エンドポイントの有効化
2. シークレットキー
3. レート制限（1 時間あたりの最大リクエスト数）
4. 保存成功時の Webhook URL
5. 保存失敗時の Webhook URL

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

## Webhook 通知

- 成功時:

  ```json
  {
    "event": "submission_success",
    "submission_id": "Flamingo内の送信ID",
    "timestamp": "Unix timestamp"
  }
  ```

- 失敗時:
  ```json
  {
    "event": "submission_failure",
    "submission_id": null,
    "timestamp": "Unix timestamp"
  }
  ```

## 投稿例

### cURL を使用した投稿例

```bash
curl -X POST \
  https://your-wordpress-site.com/wp-json/petanco-api/v1/submit \
  -H 'Content-Type: application/json' \
  -H 'X-Petanco-API-Key: your-secret-key-here' \
  -d '{
    "subject": "サンプル特典",
    "name": "山田太郎",
    "email": "yamada@example.com",
    "tel": "03-1234-5678",
    "zip": "100-0001",
    "pref": "東京都",
    "city": "千代田区",
    "address1": "丸の内1-1-1",
    "address2": "サンプルビル101",
    "campaign_id": "CAMP001",
    "benefit_id": "BENEFIT001",
    "player_id": "PLAYER004"
  }'
```

### React を使用した投稿例

```jsx
import axios from "axios";

const submitForm = async (formData) => {
  try {
    const response = await axios.post(
      "https://your-wordpress-site.com/wp-json/petanco-api/v1/submit",
      formData,
      {
        headers: {
          "Content-Type": "application/json",
          "X-Petanco-API-Key": "your-secret-key-here",
        },
      }
    );
    console.log("成功:", response.data);
  } catch (error) {
    console.error("エラー:", error.response.data);
  }
};

// 使用例
const handleSubmit = () => {
  const formData = {
    subject: "サンプル特典",
    name: "山田太郎",
    email: "yamada@example.com",
    tel: "03-1234-5678",
    zip: "100-0001",
    pref: "東京都",
    city: "千代田区",
    address1: "丸の内1-1-1",
    address2: "サンプルビル101",
    campaign_id: "CAMP001",
    benefit_id: "BENEFIT001",
    player_id": "PLAYER004",
  };
  submitForm(formData);
};
```

## 注意事項

- このプラグインを使用するには、Flamingo プラグインがインストールされ、有効化されている必要があります。
- デバッグモードが有効な場合、ログが WordPress のエラーログに記録されます。
- CORS 設定により、すべてのオリジンからの POST リクエストが許可されています。
- 実際の使用時は、`your-wordpress-site.com`を実際の WordPress サイトの URL に、`your-secret-key-here`を設定した実際のシークレットキーに置き換えてください。

## インストール方法

1. プラグインファイルを WordPress の`wp-content/plugins/`ディレクトリにアップロードします。
2. WordPress 管理画面でプラグインを有効化します。
3. Flamingo 管理画面内の「Petanco 連携設定」ページで必要な設定を行います。
