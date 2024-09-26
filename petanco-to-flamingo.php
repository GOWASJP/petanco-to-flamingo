<?php
/**
 * Plugin Name: Petanco to Flamingo
 * Plugin URI: https://doc.petanco.net/for-organizer/option/3150/
 * Description: Petancoから送信された応募データをFlamingoに保存します。
 * Version: 1.0.5
 * Author: Petanco
 * Author URI: https://petanco.net
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: petanco-to-flamingo
 *
 * このプラグインは、PetancoのシステムからのAPIリクエストを受け取り、
 * 送信されたデータをFlamingoに保存します。また、レート制限、認証機能も提供します。
 *
 * @package Petanco_to_Flamingo
 */

// 直接アクセスされた場合は終了します。
if (!defined('ABSPATH')) {
	exit;
}

/**
 * デバッグモードを制御する定数
 * trueの場合、デバッグログが出力されます。
 */
define('PETANCO_API_DEBUG', false);

/**
 * プラグインのバージョンを定義する定数
 * バージョン管理とアップデートチェックに使用されます。
 */
define('PETANCO_TO_FLAMINGO_VERSION', '1.0.5');

/**
 * GitHub APIのURLを定義する定数
 * 最新バージョンの取得に使用されます。
 */
define('PETANCO_TO_FLAMINGO_GITHUB_API_URL', 'https://api.github.com/repos/GOWASJP/petanco-to-flamingo/releases/latest');


/**
 * デフォルトのレート制限値
 *
 * この定数は、APIリクエストのレート制限のデフォルト値を定義します。
 * 値は1時間あたりの最大リクエスト数を表します。
 * ユーザーが設定を変更していない場合、このデフォルト値が使用されます。
 *
 * @var int
 */
define('PETANCO_DEFAULT_RATE_LIMIT', 300);


/**
 * デバッグログ関数
 *
 * @param string $message ログに記録するメッセージ
 * @return void
 */
function petanco_api_debug_log($message) {
	if (PETANCO_API_DEBUG) {
		error_log(sprintf(__('Petanco to Flamingo: %s', 'petanco-to-flamingo'), $message));
	}
}

/**
 * 管理画面の初期化時に実行される関数
 * 
 * バージョンチェック機能を管理画面に追加します。
 *
 * @return void
 */
function petanco_api_admin_init() {
    add_action('admin_notices', 'petanco_api_version_check');
}
add_action('admin_init', 'petanco_api_admin_init');

/**
 * プラグインのバージョンをチェックし、新しいバージョンがある場合は通知を表示
 *
 * GitHub APIを使用して最新バージョンを取得し、現在のバージョンと比較します。
 * 新しいバージョンが利用可能な場合、管理画面に通知を表示します。
 *
 * @return void
 */
function petanco_api_version_check() {
    if (false === get_transient('petanco_to_flamingo_version_check')) {
        $response = wp_remote_get(PETANCO_TO_FLAMINGO_GITHUB_API_URL, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'Petanco to Flamingo Plugin'
            ]
        ]);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            set_transient('petanco_to_flamingo_version_check', 'error', HOUR_IN_SECONDS);
            add_action('admin_notices', 'petanco_api_error_notice');
            return;
        }

        $data = json_decode(wp_remote_retrieve_body($response));
        if (empty($data->tag_name)) {
            set_transient('petanco_to_flamingo_version_check', 'error', HOUR_IN_SECONDS);
            add_action('admin_notices', 'petanco_api_error_notice');
            return;
        }

        $latest_version = ltrim($data->tag_name, 'v');
        set_transient('petanco_to_flamingo_latest_version', $latest_version, DAY_IN_SECONDS);
        set_transient('petanco_to_flamingo_version_check', time(), DAY_IN_SECONDS);
    }

    $latest_version = get_transient('petanco_to_flamingo_latest_version');
    if ($latest_version && version_compare(PETANCO_TO_FLAMINGO_VERSION, $latest_version, '<')) {
        add_action('admin_notices', 'petanco_api_update_notice');
    }
}



/**
 * 管理画面に更新通知を表示
 *
 * 新しいバージョンのプラグインが利用可能な場合、
 * 管理画面に警告通知を表示します。通知には最新バージョン番号と
 * ダウンロードリンクが含まれます。
 *
 * @return void
 */
function petanco_api_update_notice() {
    $latest_version = get_transient('petanco_to_flamingo_latest_version');
    $message = sprintf(
        __('Petanco to Flamingo プラグインの新しいバージョン（%s）が利用可能です。<a href="%s" target="_blank">こちらからダウンロード</a>してください。', 'petanco-to-flamingo'),
        $latest_version,
        'https://github.com/GOWASJP/petanco-to-flamingo/releases/latest'
    );
    echo '<div class="notice notice-warning is-dismissible"><p>' . $message . '</p></div>';
}

/**
 * エラー通知を管理画面に表示
 *
 * バージョンチェック中にエラーが発生した場合、
 * 管理画面にエラー通知を表示します。通知には
 * エラーの概要とログ確認の指示が含まれます。
 *
 * @return void
 */
function petanco_api_error_notice() {
    $class = 'notice notice-error is-dismissible';
    $message = __('Petanco to Flamingoプラグインのバージョンチェック中にエラーが発生しました。インターネット接続を確認し、しばらくしてからもう一度お試しください。', 'petanco-to-flamingo');
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}


/**
 * SSL環境を確認する関数
 *
 * @return bool SSL環境であればtrue、そうでなければfalse
 */
if (!is_ssl()) {
    petanco_api_debug_log(__('SSL環境が検出されませんでした。プラグインを無効化します。', 'petanco-to-flamingo'));
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(__('このプラグインはSSL環境でのみ使用できます。プラグインを有効化するには、SSLを有効にしてください。', 'petanco-to-flamingo'), 'プラグイン有効化エラー', array('back_link' => true));
}


/**
 * プラグイン有効化時の処理
 *
 * この関数は、プラグインが有効化されたときに実行されます。
 * SSL環境のチェック、プラグインの有効化状態の設定、
 * およびデバッグログの記録を行います。
 *
 * @return void
 */
function petanco_api_extension_activate() {
    petanco_api_debug_log(__('プラグイン有効化プロセスを開始します。', 'petanco-to-flamingo'));
    
    if (!is_ssl()) {
        petanco_api_debug_log(__('SSL環境が検出されませんでした。プラグインを無効化します。', 'petanco-to-flamingo'));
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('このプラグインはSSL環境でのみ使用できます。プラグインを有効化するには、SSLを有効にしてください。', 'petanco-to-flamingo'), 'プラグイン有効化エラー', array('back_link' => true));
    } else {
        petanco_api_debug_log(__('SSL環境が正常に検出されました。プラグインを有効化します。', 'petanco-to-flamingo'));
        update_option('petanco_api_ssl_enabled', true);
    }

    add_option('petanco_api_extension_activated', true);
    petanco_api_debug_log(__('プラグインが正常に有効化されました。', 'petanco-to-flamingo'));
}
register_activation_hook(__FILE__, 'petanco_api_extension_activate');

/**
 * プラグインの初期化
 *
 * この関数は、プラグインの基本的な初期化処理を行います。
 * 具体的には以下の処理を実行します：
 * 1. SSL環境のチェック
 * 2. 多言語対応の準備
 * 3. Flamingoプラグインの存在確認
 * 4. REST APIルートの登録
 * 5. 成功通知の表示
 *
 * @return void
 */
function petanco_api_extension_init() {
    petanco_api_debug_log(__('初期化が開始されました。', 'petanco-to-flamingo'));

	// SSL環境の再確認
    if (!is_ssl() || !get_option('petanco_api_ssl_enabled', false)) {
        petanco_api_debug_log(__('警告: SSL環境が検出されないか、有効化されていません。', 'petanco-to-flamingo'));
        add_action('admin_notices', 'petanco_api_ssl_warning_notice');
        return;
    }

    // 多言語対応の準備
    load_plugin_textdomain('petanco-to-flamingo', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Flamingoプラグインの存在確認
    if (!defined('FLAMINGO_VERSION')) {
        petanco_api_debug_log(__('Flamingo定数が定義されていません。', 'petanco-to-flamingo'));
        add_action('admin_notices', 'petanco_api_extension_admin_notice');
        return;
    }

    petanco_api_debug_log(sprintf(__('フラミンゴが正常に検出されました. バージョン: %s', 'petanco-to-flamingo'), FLAMINGO_VERSION));

    // REST APIルートの登録
    add_action('rest_api_init', 'petanco_api_register_route');

    // 成功通知の表示
    add_action('admin_notices', 'petanco_api_extension_success_notice');
}
add_action('plugins_loaded', 'petanco_api_extension_init', 20);

/**
 * SSL警告通知
 *
 * @return void
 */
function petanco_api_ssl_warning_notice() {
    $class = 'notice notice-warning is-dismissible';
    $message = __('Petanco to Flamingo プラグインは SSL 環境で動作するように設計されていますが、現在 SSL が検出されないか有効になっていません。プラグインが正しく機能しない可能性があります。サイトの SSL 設定を確認してください。', 'petanco-to-flamingo');
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}

/**
 * エラー通知関数
 *
 * @return void
 */
function petanco_api_extension_admin_notice() {
	echo '<div class="error"><p>' . __('Petanco to Flamingoを使用するには、Flamingoプラグインをインストールし、有効化する必要があります。', 'petanco-to-flamingo') . '</p></div>';
	petanco_api_debug_log(__('管理エラー通知が表示されました。', 'petanco-to-flamingo'));
}

/**
 * 成功通知関数
 *
 * @return void
 */
function petanco_api_extension_success_notice() {
	if (get_option('petanco_api_extension_activated')) {
		echo '<div class="updated"><p>' . __('Petanco to Flamingoは正常にアクティベートされ、Flamingoが検出されました。', 'petanco-to-flamingo') . '</p></div>';
		petanco_api_debug_log(__('アクティベート成功通知', 'petanco-to-flamingo'));
		delete_option('petanco_api_extension_activated');
	}
}

/**
 * フォーム送信ハンドラ
 *
 * 受信したデータを検証し、Flamingo に保存します。
 *
 * @param WP_REST_Request $request リクエストオブジェクト
 * @return WP_REST_Response|WP_Error 成功時はレスポンスオブジェクト、失敗時はエラーオブジェクト
 */
function petanco_api_handle_submission($request) {
    $params = $request->get_params();

    $validation_errors = petanco_api_validate_submission($params);
    if (!empty($validation_errors)) {
        petanco_api_debug_log(__('検証に失敗しました。', 'petanco-to-flamingo'));
        return new WP_Error(
            'validation_failed',
            __('入力データが無効です。', 'petanco-to-flamingo'),
            array(
                'status' => 400,
                'errors' => $validation_errors,
                'callout' => current_time('mysql')
            )
        );
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
			'benefit_id' => sanitize_text_field($params['benefit_id'] ?? ''),
			'player_id' => sanitize_text_field($params['player_id'] ?? ''),
		),
		'body' => sprintf(
			"特典: %s\n名前: %s\nメール: %s\n電話番号: %s\n郵便番号: %s\n都道府県: %s\n市区町村: %s\n住所1: %s\n住所2: %s\nキャンペーンID: %s\n特典ID: %s\nプレイヤーID: %s",
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
			sanitize_text_field($params['benefit_id'] ?? ''),
			sanitize_text_field($params['player_id'] ?? ''),
		),
		'meta' => array(
			'remote_ip' => $_SERVER['REMOTE_ADDR'],
			'user_agent' => $_SERVER['HTTP_USER_AGENT'],
		)
	);

	petanco_api_debug_log('Attempting to save submission with data: ' . print_r($submission_data, true));

    $submission = Flamingo_Inbound_Message::add($submission_data);

    if ($submission) {
        petanco_api_debug_log(__('送信が正常に完了しました。', 'petanco-to-flamingo') . ' ID: ' . $submission->id());
        $response = new WP_REST_Response(array(
            'message' => __('応募が正常に完了しました。', 'petanco-to-flamingo'),
            'callout' => current_time('mysql')
        ), 200);
        $response->set_headers(array('Cache-Control' => 'no-cache, no-store, must-revalidate'));

        return $response;
    } else {
        petanco_api_debug_log(__('送信の保存に失敗しました。', 'petanco-to-flamingo'));

        return new WP_Error(
            'submission_failed',
            __('送信の保存に失敗しました。', 'petanco-to-flamingo'),
            array(
                'status' => 500,
                'callout' => current_time('mysql')
            )
        );
    }
}


/**
 * 送信データのバリデーション
 *
 * 必須フィールドの存在とメールアドレスの形式を確認します。
 *
 * @param array $params 送信パラメータ
 * @return array バリデーションエラーの配列。エラーがない場合は空の配列
 */
function petanco_api_validate_submission($params) {
	$required_fields = ['subject', 'name', 'email', 'tel', 'zip', 'pref', 'city', 'address1', 'campaign_id', 'benefit_id', 'player_id'];
	$errors = array();

	foreach ($required_fields as $field) {
		if (empty($params[$field])) {
			$errors[$field] = sprintf(__('%sは必須です。', 'petanco-to-flamingo'), $field);
		}
	}

	// 最小限のメールアドレス形式チェック
	if (!empty($params['email']) && !is_email($params['email'])) {
		$errors['email'] = __('有効なメールアドレスを入力してください。', 'petanco-to-flamingo');
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
		__('Petanco連携設定', 'petanco-to-flamingo'),
		__('Petanco連携設定', 'petanco-to-flamingo'),
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
			submit_button(__('保存', 'petanco-to-flamingo'));
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
		__('設定', 'petanco-to-flamingo'),
		'petanco_api_general_section_callback',
		'petanco-api-settings'
	);

	// 既存のフィールド
	add_settings_field(
		'petanco_api_enable_endpoint',
		__('APIエンドポイントの有効化', 'petanco-to-flamingo'),
		'petanco_api_enable_endpoint_callback',
		'petanco-api-settings',
		'petanco_api_general_section'
	);

	add_settings_field(
		'petanco_api_secret_key',
		__('シークレットキー', 'petanco-to-flamingo'),
		'petanco_api_secret_key_callback',
		'petanco-api-settings',
		'petanco_api_general_section'
	);

	add_settings_field(
		'petanco_api_rate_limit',
		__('レート制限', 'petanco-to-flamingo'),
		'petanco_api_rate_limit_callback',
		'petanco-api-settings',
		'petanco_api_general_section'
	);

    add_settings_field(
        'petanco_api_version_check_status',
        __('バージョンチェック', 'petanco-to-flamingo'),
        'petanco_api_version_check_status_callback',
        'petanco-api-settings',
        'petanco_api_general_section'
    );

}
add_action('admin_init', 'petanco_api_settings_init');


/**
 * 設定セクションのコールバック
 *
 * 設定ページの説明文を表示します。
 *
 * @return void
 */
function petanco_api_general_section_callback() {
	echo '<p>' . __('Petanco連携の全般的な設定', 'petanco-to-flamingo') . '</p>';
}

/**
 * APIエンドポイント有効化設定のコールバック
 *
 * APIエンドポイントの有効/無効を切り替えるチェックボックスと
 * エンドポイントURLの表示、コピー機能を提供します。
 *
 * @return void
 */
function petanco_api_enable_endpoint_callback() {
    $options = get_option('petanco_api_settings');
    $checked = isset($options['enable_endpoint']) ? $options['enable_endpoint'] : '1';
    $endpoint_url = rest_url('petanco-api/v1/submit');

    echo '<input type="checkbox" id="petanco_api_enable_endpoint" name="petanco_api_settings[enable_endpoint]" value="1"' . checked(1, $checked, false) . '/>';
    echo '<label for="petanco_api_enable_endpoint">' . __('REST API エンドポイントを有効にします。', 'petanco-to-flamingo') . '</label>';

    echo '<div id="petanco_api_endpoint_url" style="margin-top: 10px;">';
    if ($checked == '1') {
        echo '<strong>' . __('エンドポイントURL:', 'petanco-to-flamingo') . '</strong><br>';
        echo '<p style="margin-bottom: 10px;"><code id="endpoint_url">' . esc_url($endpoint_url) . '</code></p>';
        echo '<button type="button" id="copy_endpoint_url" class="button button-secondary">' . __('URLをコピー', 'petanco-to-flamingo') . '</button>';
        echo '<span id="copy_message" style="margin-left: 10px; display: none; color: green;">' . __('コピーしました！', 'petanco-to-flamingo') . '</span>';
        echo '<p class="description">' . __('コピーしてPetanco側に設定してください。', 'petanco-to-flamingo') .'</p>';
    }
    echo '</div>';

    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#petanco_api_enable_endpoint').change(function() {
            if (this.checked) {
                $('#petanco_api_endpoint_url').html('<strong><?php echo esc_js(__('エンドポイントURL:', 'petanco-to-flamingo')); ?></strong><br><p style="margin-bottom: 10px;"><code id="endpoint_url"><?php echo esc_js($endpoint_url); ?></code></p><button type="button" id="copy_endpoint_url" class="button button-secondary"><?php echo esc_js(__('URLをコピー', 'petanco-to-flamingo')); ?></button><span id="copy_message" style="margin-left: 10px; display: none; color: green;"><?php echo esc_js(__('コピーしました！', 'petanco-to-flamingo')); ?></span><p class="description"><?php echo esc_js(__('コピーしてPetanco側に設定してください。', 'petanco-to-flamingo')); ?></p>');
                addCopyButtonListener();
            } else {
                $('#petanco_api_endpoint_url').empty();
            }
        });

        function addCopyButtonListener() {
            $('#copy_endpoint_url').click(function() {
                var $temp = $("<input>");
                $("body").append($temp);
                $temp.val($('#endpoint_url').text()).select();
                document.execCommand("copy");
                $temp.remove();
                $('#copy_message').fadeIn().delay(2000).fadeOut();
            });
        }

        addCopyButtonListener();
    });
    </script>
    <?php
}

/**
 * シークレットキーを暗号化する
 *
 * @param string $key 暗号化するシークレットキー
 * @return string 暗号化されたシークレットキー
 */
function petanco_api_encrypt_secret_key($key) {
    $salt = wp_salt('auth');
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($key, 'AES-256-CBC', $salt, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * 暗号化されたシークレットキーを復号化する
 *
 * @param string $encrypted_key 暗号化されたシークレットキー
 * @return string 復号化されたシークレットキー
 */
function petanco_api_decrypt_secret_key($encrypted_data) {
    $salt = wp_salt('auth');
    $decoded = base64_decode($encrypted_data);
    $iv = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $salt, 0, $iv);
}

/**
 * シークレットキー設定のコールバック
 *
 * @return void
 */
function petanco_api_secret_key_callback() {
    $options = get_option('petanco_api_settings');
    $encrypted_key = isset($options['secret_key']) ? $options['secret_key'] : '';
    $decrypted_key = $encrypted_key ? petanco_api_decrypt_secret_key($encrypted_key) : '';
    ?>
    <input type="text" id="petanco_api_secret_key" name="petanco_api_settings[secret_key]" value="<?php echo esc_attr($decrypted_key); ?>" class="regular-text">
    <p class="description"><?php _e('Petanco側で発行したシークレットキーを入力してください。', 'petanco-to-flamingo'); ?></p>
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
    $sanitized_input['secret_key'] = !empty($input['secret_key']) ? petanco_api_encrypt_secret_key(sanitize_text_field($input['secret_key'])) : '';
    $sanitized_input['rate_limit'] = absint($input['rate_limit']);
    
    return $sanitized_input;
}

/**
 * レート制限設定のコールバック
 *
 * レート制限値を設定するための数値入力フィールドを表示します。
 *
 * @return void
 */
function petanco_api_rate_limit_callback() {
    $options = get_option('petanco_api_settings');
    $rate_limit = isset($options['rate_limit']) ? intval($options['rate_limit']) : PETANCO_DEFAULT_RATE_LIMIT;
    ?>
    <input type="number" id="petanco_api_rate_limit" name="petanco_api_settings[rate_limit]" value="<?php echo esc_attr($rate_limit); ?>" min="1" max="3600">
    <p class="description"><?php _e('1時間あたりの最大応募可能数を設定してください。デフォルト値は300です。', 'petanco-to-flamingo'); ?></p>
    <?php
}

/**
 * レート制限のチェック
 *
 * @return bool レート制限内ならtrue、超過していればfalse
 */
function petanco_api_check_rate_limit() {
    $options = get_option('petanco_api_settings');
    $rate_limit = isset($options['rate_limit']) ? intval($options['rate_limit']) : PETANCO_DEFAULT_RATE_LIMIT;

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

/**
 * 現在のバージョンチェックの状態を確認
 *
 * 現在のバージョンチェックの状態を確認できるようにします。
 *
 * @return void
 */
function petanco_api_version_check_status_callback() {
    $version_check = get_transient('petanco_to_flamingo_version_check');
    $latest_version = get_transient('petanco_to_flamingo_latest_version');

    echo '<p><strong>' . __('現在のプラグインバージョン:', 'petanco-to-flamingo') . '</strong> ' . PETANCO_TO_FLAMINGO_VERSION . '</p>';

    if ($version_check === false) {
        echo '<p>' . __('バージョンチェックはまだ実行されていません。', 'petanco-to-flamingo') . '</p>';
    } elseif ($version_check === 'error') {
        echo '<p style="color: red;">' . __('前回のバージョンチェックでエラーが発生しました。', 'petanco-to-flamingo') . '</p>';
    } else {
        $check_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $version_check);
        echo '<p>' . sprintf(__('最終バージョンチェック: %s', 'petanco-to-flamingo'), $check_time) . '</p>';
        
        if ($latest_version) {
            echo '<p><strong>' . __('最新バージョン:', 'petanco-to-flamingo') . '</strong> ' . esc_html($latest_version) . '</p>';
            
            if (version_compare(PETANCO_TO_FLAMINGO_VERSION, $latest_version, '<')) {
                echo '<p style="color: orange;">' . __('新しいバージョンが利用可能です。', 'petanco-to-flamingo') . '</p>';
                
                // ダウンロードリンクの追加
                $download_url = 'https://github.com/GOWASJP/petanco-to-flamingo/releases/latest';
                echo '<p><a href="' . esc_url($download_url) . '" class="button button-primary" target="_blank">' . 
                    __('最新バージョンをダウンロード', 'petanco-to-flamingo') . '</a></p>';
                
                echo '<p class="description">' . __('注意: ダウンロード後、既存のプラグインを削除し、新しいバージョンをアップロードしてください。', 'petanco-to-flamingo') . '</p>';
            } else {
                echo '<p style="color: green;">' . __('プラグインは最新です。', 'petanco-to-flamingo') . '</p>';
            }
        } else {
            echo '<p>' . __('最新バージョン情報は利用できません。', 'petanco-to-flamingo') . '</p>';
        }
    }

    echo '<button type="button" id="check_version_now" class="button button-secondary">' . __('今すぐチェック', 'petanco-to-flamingo') . '</button>';
    echo '<span id="version_check_message" style="margin-left: 10px; display: none;"></span>';

    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#check_version_now').click(function() {
            var button = $(this);
            var message = $('#version_check_message');
            button.prop('disabled', true);
            message.text('<?php echo esc_js(__('チェック中...', 'petanco-to-flamingo')); ?>').show();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'petanco_check_version_now'
                },
                success: function(response) {
                    if (response.success) {
                        message.text('<?php echo esc_js(__('バージョンチェックが完了しました。ページをリロードしてください。', 'petanco-to-flamingo')); ?>').css('color', 'green');
                    } else {
                        message.text('<?php echo esc_js(__('エラーが発生しました。もう一度お試しください。', 'petanco-to-flamingo')); ?>').css('color', 'red');
                    }
                },
                error: function() {
                    message.text('<?php echo esc_js(__('エラーが発生しました。もう一度お試しください。', 'petanco-to-flamingo')); ?>').css('color', 'red');
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}

add_action('wp_ajax_petanco_check_version_now', 'petanco_api_check_version_now');

function petanco_api_check_version_now() {
    delete_transient('petanco_to_flamingo_version_check');
    delete_transient('petanco_to_flamingo_latest_version');
    petanco_api_version_check();
    wp_send_json_success();
}

/**
 * REST API ルートの登録
 *
 * プラグイン設定に基づいて、REST API ルートを登録します。
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
        petanco_api_debug_log(__('REST API ルートが登録されました。', 'petanco-to-flamingo'));
    } else {
        petanco_api_debug_log(__('REST API ルートが登録されていません(設定で無効になっています)。', 'petanco-to-flamingo'));
    }
}
add_action('rest_api_init', 'petanco_api_register_route');

/**
 * API認証チェック
 *
 * @param WP_REST_Request $request リクエストオブジェクト
 * @return bool|WP_Error 認証成功時はtrue、失敗時はWP_Error
 */
function petanco_api_check_permission($request) {
    $options = get_option('petanco_api_settings');
    $encrypted_key = isset($options['secret_key']) ? $options['secret_key'] : '';
    $secret_key = $encrypted_key ? petanco_api_decrypt_secret_key($encrypted_key) : '';

    $provided_key = $request->get_header('X-Petanco-API-Key');

    if (empty($secret_key) || $provided_key !== $secret_key) {
        petanco_api_debug_log(__('API認証に失敗しました。', 'petanco-to-flamingo'));
        return new WP_Error('rest_forbidden', __('アクセスが拒否されました。', 'petanco-to-flamingo'), array('status' => 403));
    }

    // レート制限のチェック
    if (!petanco_api_check_rate_limit()) {
        petanco_api_debug_log(__('レート制限を超過しました。', 'petanco-to-flamingo'));
        return new WP_Error('rate_limit_exceeded', __('レート制限を超えました。しばらくしてからもう一度お試しください。', 'petanco-to-flamingo'), array('status' => 429));
    }

    return true;
}


/**
 * テストのCORS設定
 *
 * @return void
 */

add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST');
        header('Access-Control-Allow-Headers: X-Petanco-API-Key, Content-Type');
        return $value;
    });
}, 15);
petanco_api_debug_log(__('CORS設定が適用されました。', 'petanco-to-flamingo'));

/**
 * 本番のCORS設定
 *
 * @return void
 */
/*
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        $origin = get_http_origin();
        $allowed_origin = 'https://petanco.io';

        if ($origin === $allowed_origin) {
            header("Access-Control-Allow-Origin: $allowed_origin");
            header('Access-Control-Allow-Methods: POST, OPTIONS');
            header('Access-Control-Allow-Headers: X-Petanco-API-Key, Content-Type');
            header('Access-Control-Allow-Credentials: true');
        } else {
            header('HTTP/1.1 403 Forbidden');
            exit;
        }

        // OPTIONSリクエスト（プリフライトリクエスト）の処理
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit;
        }

        return $value;
    }, 20);
});
petanco_api_debug_log(__('CORS設定が適用されました。', 'petanco-to-flamingo'));
*/