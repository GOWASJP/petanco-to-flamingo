<?php
/**
 * Plugin Name: Plugin to connect Petanco to Flamingo
 * Plugin URI: https://doc.petanco.net/for-organizer/option/3150/
 * Description: Petancoから送信された応募データをFlamingoに保存します。
 * Version: 1.0.3
 * Author: Petanco
 * Author URI: https://petanco.net
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: petanco-flamingo-api
 *
 * このプラグインは、PetancoのシステムからのAPIリクエストを受け取り、
 * 送信されたデータをFlamingoに保存します。また、Webhook通知、
 * レート制限、認証機能も提供します。
 *
 * @package Flamingo_API_Extension_for_Petanco
 */

// 直接アクセスされた場合は終了します。
if (!defined('ABSPATH')) {
	exit;
}

// デバッグ用の定数
define('PETANCO_API_DEBUG', true);

/**
 * デバッグログ関数
 *
 * @param string $message ログに記録するメッセージ
 * @return void
 */
function petanco_api_debug_log($message) {
	if (PETANCO_API_DEBUG) {
		error_log(sprintf(__('Flamingo API Extension for Petanco: %s', 'petanco-flamingo-api'), $message));
	}
}

/**
 * プラグイン有効化時の処理
 *
 * @return void
 */
function petanco_api_extension_activate() {
	add_option('petanco_api_extension_activated', true);
	petanco_api_debug_log(__('Plugin activated', 'petanco-flamingo-api'));
}
register_activation_hook(__FILE__, 'petanco_api_extension_activate');

/**
 * プラグインの初期化
 *
 * @return void
 */
function petanco_api_extension_init() {
	petanco_api_debug_log(__('Initialization started', 'petanco-flamingo-api'));

	load_plugin_textdomain('petanco-flamingo-api', false, dirname(plugin_basename(__FILE__)) . '/languages');

	if (!defined('FLAMINGO_VERSION')) {
		petanco_api_debug_log(__('Flamingo constant not defined', 'petanco-flamingo-api'));
		add_action('admin_notices', 'petanco_api_extension_admin_notice');
		return;
	}

	petanco_api_debug_log(sprintf(__('Flamingo detected successfully. Version: %s', 'petanco-flamingo-api'), FLAMINGO_VERSION));

	add_action('rest_api_init', 'petanco_api_register_route');
	add_action('admin_notices', 'petanco_api_extension_success_notice');
}
add_action('plugins_loaded', 'petanco_api_extension_init', 20);

/**
 * エラー通知関数
 *
 * @return void
 */
function petanco_api_extension_admin_notice() {
	echo '<div class="error"><p>' . __('Flamingo API Extensionを使用するには、Flamingoプラグインをインストールし、有効化する必要があります。', 'petanco-flamingo-api') . '</p></div>';
	petanco_api_debug_log(__('Admin error notice displayed', 'petanco-flamingo-api'));
}

/**
 * 成功通知関数
 *
 * @return void
 */
function petanco_api_extension_success_notice() {
	if (get_option('petanco_api_extension_activated')) {
		echo '<div class="updated"><p>' . __('Flamingo API Extensionは正常にアクティベートされ、Flamingoが検出されました。', 'petanco-flamingo-api') . '</p></div>';
		petanco_api_debug_log(__('Admin success notice displayed', 'petanco-flamingo-api'));
		delete_option('petanco_api_extension_activated');
	}
}

/**
 * フォーム送信ハンドラ
 *
 * @param WP_REST_Request $request リクエストオブジェクト
 * @return WP_REST_Response|WP_Error レスポンスまたはエラーオブジェクト
 */
function petanco_api_handle_submission($request) {
	$params = $request->get_params();

	$validation_errors = petanco_api_validate_submission($params);
	if (!empty($validation_errors)) {
		petanco_api_debug_log(__('Validation failed', 'petanco-flamingo-api'));
		return new WP_Error('validation_failed', __('入力データが無効です。', 'petanco-flamingo-api'), array('status' => 400, 'errors' => $validation_errors));
	}

	$submission_data = array(
		'channel' => 'petanco',
		'subject' => sanitize_text_field($params['subject'] ?? ''),
		'from' => sanitize_text_field($params['name'] ?? '') . ' <' . sanitize_email($params['email'] ?? '') . '>',
		'from_name' => sanitize_text_field($params['name'] ?? ''),
		'from_email' => sanitize_email($params['email'] ?? ''),
		'fields' => array(
			'subject' => sanitize_text_field($params['subject'] ?? ''),
			'name' => sanitize_text_field($params['name'] ?? ''),
			'email' => sanitize_email($params['email'] ?? ''),
			'tel' => sanitize_text_field($params['tel'] ?? ''),
			'zip' => sanitize_text_field($params['zip'] ?? ''),
			'pref' => sanitize_text_field($params['pref'] ?? ''),
			'city' => sanitize_text_field($params['city'] ?? ''),
			'address1' => sanitize_text_field($params['address1'] ?? ''),
			'address2' => sanitize_text_field($params['address2'] ?? ''),
			'campaign_id' => sanitize_text_field($params['campaign_id'] ?? ''),
			'benefit_id' => sanitize_text_field($params['benefit_id'] ?? '')
		),
		'body' => sprintf(
			"特典: %s\n名前: %s\nメール: %s\n電話番号: %s\n郵便番号: %s\n都道府県: %s\n市区町村: %s\n住所1: %s\n住所2: %s\nキャンペーンID: %s\n特典ID: %s",
			sanitize_text_field($params['subject'] ?? ''),
			sanitize_text_field($params['name'] ?? ''),
			sanitize_email($params['email'] ?? ''),
			sanitize_text_field($params['tel'] ?? ''),
			sanitize_text_field($params['zip'] ?? ''),
			sanitize_text_field($params['pref'] ?? ''),
			sanitize_text_field($params['city'] ?? ''),
			sanitize_text_field($params['address1'] ?? ''),
			sanitize_text_field($params['address2'] ?? ''),
			sanitize_text_field($params['campaign_id'] ?? ''),
			sanitize_text_field($params['benefit_id'] ?? '')
		),
		'meta' => array(
			'remote_ip' => $_SERVER['REMOTE_ADDR'],
			'user_agent' => $_SERVER['HTTP_USER_AGENT'],
		)
	);

	petanco_api_debug_log('Attempting to save submission with data: ' . print_r($submission_data, true));

	$submission = Flamingo_Inbound_Message::add($submission_data);

	if ($submission) {
		petanco_api_debug_log(__('Submission saved successfully', 'petanco-flamingo-api') . ' ID: ' . $submission->id());
		$response = new WP_REST_Response(array('message' => __('送信が正常に保存されました。', 'petanco-flamingo-api')), 200);
		$response->set_headers(array('Cache-Control' => 'no-cache, no-store, must-revalidate'));

		// 成功時のWebhookを呼び出し
		petanco_api_call_webhook('success', $submission->id());

		return $response;
	} else {
		petanco_api_debug_log(__('Failed to save submission', 'petanco-flamingo-api'));

		// 失敗時のWebhookを呼び出し
		petanco_api_call_webhook('failure', null);

		return new WP_Error('submission_failed', __('送信の保存に失敗しました。', 'petanco-flamingo-api'), array('status' => 500));
	}
 }

 /**
  * Webhookの呼び出し
  *
  * @param string $type 'success' または 'failure'
  * @param int|null $submission_id 送信ID（成功時のみ）
  * @return void
  */
 function petanco_api_call_webhook($type, $submission_id) {
	$options = get_option('petanco_api_settings');
	$webhook_url = $type === 'success' ? $options['success_webhook'] : $options['failure_webhook'];

	if (empty($webhook_url)) {
		return;
	}

	$body = array(
		'event' => $type === 'success' ? 'submission_success' : 'submission_failure',
		'submission_id' => $submission_id,
		'timestamp' => current_time('timestamp')
	);

	wp_remote_post($webhook_url, array(
		'body' => wp_json_encode($body),
		'headers' => array('Content-Type' => 'application/json'),
	));
 }

 /**
  * 送信データのバリデーション（フロントチェックが前提）
  *
  * @param array $params 送信パラメータ
  * @return array バリデーションエラーの配列
  */
 function petanco_api_validate_submission($params) {
	$required_fields = ['subject', 'name', 'email', 'tel', 'zip', 'pref', 'city', 'address1', 'campaign_id', 'benefit_id'];
	$errors = array();

	foreach ($required_fields as $field) {
		if (empty($params[$field])) {
			$errors[$field] = sprintf(__('%sは必須です。', 'petanco-flamingo-api'), $field);
		}
	}

	// 最小限のメールアドレス形式チェック
	if (!empty($params['email']) && !is_email($params['email'])) {
		$errors['email'] = __('有効なメールアドレスを入力してください。', 'petanco-flamingo-api');
	}

	return $errors;
 }

/**
  * 設定ページの追加
  *
  * @return void
  */
function petanco_api_add_settings_page() {
	add_submenu_page(
		'flamingo',
		__('Petanco連携設定', 'petanco-flamingo-api'),
		__('Petanco連携設定', 'petanco-flamingo-api'),
		'manage_options',
		'petanco-api-settings',
		'petanco_api_settings_page'
	);
}
add_action('admin_menu', 'petanco_api_add_settings_page');

/**
 * 設定ページの表示
 *
 * @return void
 */
function petanco_api_settings_page() {
	?>
	<div class="wrap">
		<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
		<?php settings_errors(); ?>
		<form action="options.php" method="post">
			<?php
			settings_fields('petanco_api_options');
			do_settings_sections('petanco-api-settings');
			submit_button(__('保存', 'petanco-flamingo-api'));
			?>
		</form>
	</div>
	<?php
}

/**
 * 設定の初期化
 *
 * @return void
 */
function petanco_api_settings_init() {
	register_setting('petanco_api_options', 'petanco_api_settings', 'petanco_api_sanitize_settings');

	add_settings_section(
		'petanco_api_general_section',
		__('設定', 'petanco-flamingo-api'),
		'petanco_api_general_section_callback',
		'petanco-api-settings'
	);

	// 既存のフィールド
	add_settings_field(
		'petanco_api_enable_endpoint',
		__('APIエンドポイントの有効化', 'petanco-flamingo-api'),
		'petanco_api_enable_endpoint_callback',
		'petanco-api-settings',
		'petanco_api_general_section'
	);

	add_settings_field(
		'petanco_api_secret_key',
		__('シークレットキー', 'petanco-flamingo-api'),
		'petanco_api_secret_key_callback',
		'petanco-api-settings',
		'petanco_api_general_section'
	);

	add_settings_field(
		'petanco_api_rate_limit',
		__('レート制限', 'petanco-flamingo-api'),
		'petanco_api_rate_limit_callback',
		'petanco-api-settings',
		'petanco_api_general_section'
	);

	// 新しいWebhookフィールドを追加
	add_settings_field(
		'petanco_api_success_webhook',
		__('保存成功時のWebhook URL', 'petanco-flamingo-api'),
		'petanco_api_webhook_callback',
		'petanco-api-settings',
		'petanco_api_general_section',
		array('key' => 'success_webhook')
	);

	add_settings_field(
		'petanco_api_failure_webhook',
		__('保存失敗時のWebhook URL', 'petanco-flamingo-api'),
		'petanco_api_webhook_callback',
		'petanco-api-settings',
		'petanco_api_general_section',
		array('key' => 'failure_webhook')
	);
 }
 add_action('admin_init', 'petanco_api_settings_init');


/**
  * Webhook入力フィールドのコールバック関数
  *
  * @param array $args コールバック引数
  * @return void
  */
 function petanco_api_webhook_callback($args) {
	$options = get_option('petanco_api_settings');
	$key = $args['key'];
	$value = isset($options[$key]) ? $options[$key] : '';
	?>
	<input type="url" id="petanco_api_<?php echo esc_attr($key); ?>" name="petanco_api_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_url($value); ?>" class="regular-text">
	<p class="description">
		<?php
		if ($key === 'success_webhook') {
			_e('保存が成功したときに通知を送信するWebhook URLを入力してください。', 'petanco-flamingo-api');
		} else {
			_e('保存が失敗したときに通知を送信するWebhook URLを入力してください。', 'petanco-flamingo-api');
		}
		?>
	</p>
	<?php
 }


/**
  * 設定セクションのコールバック
  *
  * @return void
  */
function petanco_api_general_section_callback() {
	echo '<p>' . __('Petanco連携の全般的な設定', 'petanco-flamingo-api') . '</p>';
}

/**
 * APIエンドポイント有効化設定のコールバック
 *
 * @return void
 */
function petanco_api_enable_endpoint_callback() {
	$options = get_option('petanco_api_settings');
	$checked = isset($options['enable_endpoint']) ? $options['enable_endpoint'] : '1';
	$endpoint_url = rest_url('petanco-api/v1/submit');

	echo '<input type="checkbox" id="petanco_api_enable_endpoint" name="petanco_api_settings[enable_endpoint]" value="1"' . checked(1, $checked, false) . '/>';
	echo '<label for="petanco_api_enable_endpoint">' . __('REST API エンドポイントを有効にします。', 'petanco-flamingo-api') . '</label>';

	echo '<div id="petanco_api_endpoint_url" style="margin-top: 10px;">';
	if ($checked == '1') {
		echo '<strong>' . __('エンドポイントURL:', 'petanco-flamingo-api') . '</strong><br>';
		echo '<code>' . esc_url($endpoint_url) . '</code>';
	}
	echo '</div>';

	?>
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#petanco_api_enable_endpoint').change(function() {
			if (this.checked) {
				$('#petanco_api_endpoint_url').html('<strong><?php echo esc_js(__('エンドポイントURL:', 'petanco-flamingo-api')); ?></strong><br><code><?php echo esc_js($endpoint_url); ?></code>');
			} else {
				$('#petanco_api_endpoint_url').empty();
			}
		});
	});
	</script>
	<?php
}

/**
 * シークレットキー設定のコールバック
 *
 * @return void
 */
function petanco_api_secret_key_callback() {
	$options = get_option('petanco_api_settings');
	$secret_key = isset($options['secret_key']) ? $options['secret_key'] : '';
	?>
	<input type="text" id="petanco_api_secret_key" name="petanco_api_settings[secret_key]" value="<?php echo esc_attr($secret_key); ?>" class="regular-text">
	<p class="description"><?php _e('Petanco側で発行したシークレットキーを入力してください。', 'petanco-flamingo-api'); ?></p>
	<?php
 }

/**
  * レート制限設定のコールバック
  *
  * @return void
  */
function petanco_api_rate_limit_callback() {
	$options = get_option('petanco_api_settings');
	$rate_limit = isset($options['rate_limit']) ? $options['rate_limit'] : 100;
	?>
	<input type="number" id="petanco_api_rate_limit" name="petanco_api_settings[rate_limit]" value="<?php echo esc_attr($rate_limit); ?>" min="1" max="3600">
	<p class="description"><?php _e('1時間あたりの最大リクエスト数を設定してください。', 'petanco-flamingo-api'); ?></p>
	<?php
}

/**
 * 設定のサニタイズ
 *
 * @param array $input ユーザー入力データ
 * @return array サニタイズされた設定データ
 */
function petanco_api_sanitize_settings($input) {
	$sanitized_input = array();
	$sanitized_input['enable_endpoint'] = isset($input['enable_endpoint']) ? '1' : '0';
	$sanitized_input['secret_key'] = sanitize_text_field($input['secret_key']);
	$sanitized_input['rate_limit'] = absint($input['rate_limit']);

	// Webhook URLのサニタイズとバリデーション
	$sanitized_input['success_webhook'] = esc_url_raw($input['success_webhook']);
	$sanitized_input['failure_webhook'] = esc_url_raw($input['failure_webhook']);

	if (!empty($sanitized_input['success_webhook']) && !wp_http_validate_url($sanitized_input['success_webhook'])) {
		add_settings_error('petanco_api_settings', 'invalid_success_webhook', __('無効な成功時Webhook URLです。', 'petanco-flamingo-api'));
	}

	if (!empty($sanitized_input['failure_webhook']) && !wp_http_validate_url($sanitized_input['failure_webhook'])) {
		add_settings_error('petanco_api_settings', 'invalid_failure_webhook', __('無効な失敗時Webhook URLです。', 'petanco-flamingo-api'));
	}

	return $sanitized_input;
 }

/**
  * REST APIルートの登録
  *
  * @return void
  */
function petanco_api_register_route() {
	$options = get_option('petanco_api_settings');
	if (!isset($options['enable_endpoint']) || $options['enable_endpoint'] == '1') {
		register_rest_route('petanco-api/v1', '/submit', array(
			'methods' => 'POST',
			'callback' => 'petanco_api_handle_submission',
			'permission_callback' => 'petanco_api_check_permission'
		));
		petanco_api_debug_log(__('REST API route registered', 'petanco-flamingo-api'));
	} else {
		petanco_api_debug_log(__('REST API route not registered (disabled in settings)', 'petanco-flamingo-api'));
	}
}

/**
 * API認証チェック
 *
 * @param WP_REST_Request $request リクエストオブジェクト
 * @return bool|WP_Error 認証成功時はtrue、失敗時はWP_Error
 */
function petanco_api_check_permission($request) {
	$options = get_option('petanco_api_settings');
	$secret_key = isset($options['secret_key']) ? $options['secret_key'] : '';

	$provided_key = $request->get_header('X-Petanco-API-Key');

	if (empty($secret_key) || $provided_key !== $secret_key) {
		petanco_api_debug_log(__('API authentication failed', 'petanco-flamingo-api'));
		return new WP_Error('rest_forbidden', __('アクセスが拒否されました。', 'petanco-flamingo-api'), array('status' => 403));
	}

	// レート制限のチェック
	if (!petanco_api_check_rate_limit()) {
		petanco_api_debug_log(__('Rate limit exceeded', 'petanco-flamingo-api'));
		return new WP_Error('rate_limit_exceeded', __('レート制限を超えました。しばらくしてからもう一度お試しください。', 'petanco-flamingo-api'), array('status' => 429));
	}

	return true;
}

/**
 * レート制限のチェック
 *
 * @return bool レート制限内ならtrue、超過していればfalse
 */
function petanco_api_check_rate_limit() {
	$options = get_option('petanco_api_settings');
	$rate_limit = isset($options['rate_limit']) ? intval($options['rate_limit']) : 60;

	$current_time = time();
	$request_count = get_transient('petanco_api_request_count');

	if ($request_count === false) {
		set_transient('petanco_api_request_count', 1, 3600);
		return true;
	}

	if ($request_count >= $rate_limit) {
		return false;
	}

	set_transient('petanco_api_request_count', $request_count + 1, 3600);
	return true;
}

// CORS設定を追加
add_action('rest_api_init', function() {
	remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
	add_filter('rest_pre_serve_request', function($value) {
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: POST');
		header('Access-Control-Allow-Headers: X-Petanco-API-Key, Content-Type');
		return $value;
	});
}, 15);

petanco_api_debug_log(__('Plugin file loaded', 'petanco-flamingo-api'));