<?php

declare(strict_types=1);

namespace Ddegner\AvifLocalSupport\Admin;

use Ddegner\AvifLocalSupport\Converter;
use Ddegner\AvifLocalSupport\Diagnostics;
use Ddegner\AvifLocalSupport\DTO\AvifSettings;
use Ddegner\AvifLocalSupport\ImageMagickCli;
use Ddegner\AvifLocalSupport\Logger;

defined('ABSPATH') || exit;

/**
 * Handles REST API routes for AVIF Local Support plugin.
 */
final class RestController
{





	private const NAMESPACE = 'aviflosu/v1';
	private const MAX_MISSING_FILES_LIMIT = 1000;
	private const MAX_MAGICK_TEST_OUTPUT_BYTES = 65536;

	private Converter $converter;
	private Logger $logger;
	private Diagnostics $diagnostics;

	public function __construct(Converter $converter, Logger $logger, Diagnostics $diagnostics)
	{
		$this->converter = $converter;
		$this->logger = $logger;
		$this->diagnostics = $diagnostics;
	}

	/**
	 * Register all REST routes.
	 */
	public function register(): void
	{
		register_rest_route(
			self::NAMESPACE ,
			'/scan-missing',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'scanMissing'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/missing-files',
			array(
				'methods' => 'GET',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'missingFiles'),
				'args' => array(
					'limit' => array(
						'required' => false,
						'type' => 'integer',
						'default' => 200,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/convert-now',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'convertNow'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/conversion-state',
			array(
				'methods' => 'GET',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'conversionState'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/apply-recommended-defaults',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'applyRecommendedDefaults'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/stop-convert',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'stopConvert'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/delete-all-avifs',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'deleteAllAvifs'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/logs',
			array(
				'methods' => 'GET',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'getLogs'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/logs/clear',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'clearLogs'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/magick-test',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'runMagickTest'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/upload-test-status',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'uploadTestStatus'),
				'args' => array(
					'attachment_id' => array(
						'required' => true,
						'type' => 'integer',
						'sanitize_callback' => 'absint',
					),
					'target_index' => array(
						'required' => false,
						'type' => 'integer',
						'default' => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/upload-test',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'uploadTest'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/playground/create',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'playgroundCreate'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/playground/preview',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'playgroundPreview'),
				'args' => array(
					'token' => array(
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/playground/apply-settings',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'playgroundApplySettings'),
			)
		);

		// ThumbHash bulk operations.
		register_rest_route(
			self::NAMESPACE ,
			'/thumbhash/stats',
			array(
				'methods' => 'GET',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'thumbhashStats'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/thumbhash/generate-all',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'thumbhashGenerateAll'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/thumbhash/stop',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'thumbhashStop'),
			)
		);

		register_rest_route(
			self::NAMESPACE ,
			'/thumbhash/delete-all',
			array(
				'methods' => 'POST',
				'permission_callback' => array($this, 'permissionManageOptions'),
				'callback' => array($this, 'thumbhashDeleteAll'),
			)
		);
	}

	public function permissionManageOptions( ?\WP_REST_Request $request = null ): bool
	{
		// Admin-only endpoint for all actions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Require a REST nonce for non-read actions to mitigate CSRF.
		if ( null === $request ) {
			return false;
		}

		$method = strtoupper( (string) $request->get_method() );
		if ( in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			return true;
		}

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}

		return '' !== $nonce && (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	public function scanMissing(\WP_REST_Request $request): \WP_REST_Response
	{
		return rest_ensure_response($this->diagnostics->computeMissingCounts());
	}

	public function missingFiles(\WP_REST_Request $request): \WP_REST_Response
	{
		$limit = (int) $request->get_param('limit');
		$limit = max(1, min(self::MAX_MISSING_FILES_LIMIT, $limit > 0 ? $limit : 200));
		return rest_ensure_response($this->diagnostics->getMissingFiles($limit));
	}

	private function normalizeUploadsPath(string $path, bool $mustExist = true): ?string
	{
		$path = str_replace("\0", '', trim($path));
		if ('' === $path) {
			return null;
		}

		$uploadDir = wp_upload_dir();
		$baseDir = (string) ($uploadDir['basedir'] ?? '');
		if ('' === $baseDir) {
			return null;
		}

		$realBase = @realpath($baseDir);
		if (false === $realBase) {
			return null;
		}

		if ($mustExist) {
			if (!file_exists($path) || !is_file($path)) {
				return null;
			}

			$realPath = @realpath($path);
			if (false === $realPath) {
				return null;
			}

			if (is_link($realPath) || is_link(dirname($realPath))) {
				return null;
			}

			$normalizedPath = str_replace('\\', '/', $realPath);
			$normalizedBase = rtrim(str_replace('\\', '/', $realBase), '/') . '/';
			if (!str_starts_with($normalizedPath, $normalizedBase)) {
				return null;
			}

			return $realPath;
		}

		$dir = dirname($path);
		if ('' === $dir || '.' === $dir) {
			return null;
		}

		$realDir = @realpath($dir);
		if (false === $realDir || is_link($realDir)) {
			return null;
		}

		$normalizedBase = rtrim(str_replace('\\', '/', $realBase), '/') . '/';
		$normalizedPath = rtrim(str_replace('\\', '/', $realDir), '/') . '/' . basename($path);
		if (!str_starts_with($normalizedPath, $normalizedBase)) {
			return null;
		}

		if (file_exists($path) && is_link($path)) {
			return null;
		}

		return $normalizedPath;
	}

	private function normalizeCliBinaryPath(string $path): string
	{
		$path = trim(str_replace(array("\0", "\r", "\n", "\t"), '', $path));
		if ('' === $path) {
			return '';
		}
		if (function_exists('wp_normalize_path')) {
			$path = wp_normalize_path($path);
		}
		if (str_contains($path, '..')) {
			return '';
		}

		$isUnixAbs = str_starts_with($path, '/');
		$isWindowsAbs = preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
		return ($isUnixAbs || $isWindowsAbs) ? $path : '';
	}

	public function convertNow(\WP_REST_Request $request): \WP_REST_Response
	{
		if ($this->converter->isConversionJobActive()) {
			return rest_ensure_response(
				array(
					'queued' => false,
					'reason' => 'already_running',
				)
			);
		}

		if (\wp_next_scheduled('aviflosu_run_on_demand')) {
			return rest_ensure_response(
				array(
					'queued' => false,
					'reason' => 'already_scheduled',
				)
			);
		}

		\wp_schedule_single_event(time() + 5, 'aviflosu_run_on_demand');
		return rest_ensure_response(
			array(
				'queued' => true,
			)
		);
	}

	public function conversionState(\WP_REST_Request $request): \WP_REST_Response
	{
		$nextOnDemand = \wp_next_scheduled('aviflosu_run_on_demand');
		$nextDaily = \wp_next_scheduled('aviflosu_daily_event');
		return rest_ensure_response(
			array(
				'active' => $this->converter->isConversionJobActive(),
				'state' => $this->converter->getConversionJobState(),
				'last_run' => $this->converter->getLastRunSummary(),
				'next_on_demand' => $nextOnDemand ? (int) $nextOnDemand : 0,
				'next_daily' => $nextDaily ? (int) $nextDaily : 0,
			)
		);
	}

	public function applyRecommendedDefaults(\WP_REST_Request $request): \WP_REST_Response
	{
		\update_option('aviflosu_quality', 83);
		\update_option('aviflosu_speed', 0);
		return rest_ensure_response(
			array(
				'quality' => 83,
				'speed' => 0,
			)
		);
	}

	public function stopConvert(\WP_REST_Request $request): \WP_REST_Response
	{
		// Set stop flag that the conversion loop checks.
		\set_transient('aviflosu_stop_conversion', true, 300); // 5 minute expiry.

		// Also unschedule any pending cron job.
		$timestamp = \wp_next_scheduled('aviflosu_run_on_demand');
		if ($timestamp) {
			\wp_unschedule_event($timestamp, 'aviflosu_run_on_demand');
		}

		return rest_ensure_response(array('stopped' => true));
	}

	public function deleteAllAvifs(\WP_REST_Request $request): \WP_REST_Response
	{
		$query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => array('image/jpeg', 'image/jpg'),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'cache_results'          => false,
			)
		);

		$attempted = 0;
		$deleted = 0;
		$processed = 0;

		foreach ($query->posts as $attachmentId) {
			$result = $this->converter->deleteAvifsForAttachment((int) $attachmentId);
			$attempted += (int) ($result['attempted'] ?? 0);
			$deleted += (int) ($result['deleted'] ?? 0);
			++$processed;
		}

		$failed = max(0, $attempted - $deleted);

		// Clear the file existence cache so frontend stops trying to serve deleted AVIFs.
		\delete_transient('aviflosu_file_cache');

		return rest_ensure_response(
			array(
				'attachments_processed' => $processed,
				'attempted' => $attempted,
				'deleted' => $deleted,
				'failed' => $failed,
			)
		);
	}

	public function getLogs(\WP_REST_Request $request): \WP_REST_Response
	{
		ob_start();
		$this->logger->renderLogsContent();
		$content = ob_get_clean();
		$content = is_string($content) ? $this->sanitizeLogsHtml($content) : '';
		return rest_ensure_response(array('content' => $content));
	}

	private function sanitizeLogsHtml(string $html): string
	{
		$allowed = array(
			'div' => array(
				'class' => true,
				'data-status' => true,
				'data-filename' => true,
				'data-search' => true,
			),
			'span' => array(
				'class' => true,
			),
			'strong' => array(),
			'a' => array(
				'href' => true,
				'target' => true,
				'rel' => true,
			),
			'p' => array(
				'class' => true,
			),
			'code' => array(
				'class' => true,
			),
		);

		return (string) wp_kses($html, $allowed);
	}

	public function clearLogs(\WP_REST_Request $request): \WP_REST_Response
	{
		$this->logger->clearLogs();
		return rest_ensure_response(array('message' => 'Logs cleared'));
	}

	public function runMagickTest(\WP_REST_Request $request): \WP_REST_Response
	{
		$path = $this->normalizeCliBinaryPath((string) get_option('aviflosu_cli_path', ''));
		$detected = $this->diagnostics->detectCliBinaries();

		if ('' === $path && !empty($detected)) {
			$path = $this->normalizeCliBinaryPath((string) ($detected[0]['path'] ?? ''));
		}

		$autoSelected = false;
		if ('' === $path) {
			$auto = ImageMagickCli::getAutoDetectedPath(null);
			if ('' !== $auto) {
				$path = $this->normalizeCliBinaryPath($auto);
				$autoSelected = true;
			}
		}

		if ('' === $path || !@file_exists($path)) {
			return new \WP_REST_Response(array('message' => 'No ImageMagick CLI path found. Set a custom path under Engine Selection.'), 400);
		}

		if (!@is_executable($path)) {
			return new \WP_REST_Response(array('message' => 'Configured CLI path is not executable.'), 400);
		}

		$strategy = ImageMagickCli::getDefineStrategy($path, null);

		$outLines = array();
		$exitCode = 127;

		if (function_exists('proc_open')) {
			$descriptor = array(
				0 => array('pipe', 'r'),
				1 => array('pipe', 'w'),
				2 => array('pipe', 'w'),
			);

			$process = @proc_open(
				array($path, '-version'),
				$descriptor,
				$pipes,
				null,
				null,
				array('bypass_shell' => true)
			);
			if (is_resource($process)) {
				@fclose($pipes[0]); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- proc_open pipe.
				$out  = (string) stream_get_contents($pipes[1]);
				$err  = (string) stream_get_contents($pipes[2]);
				@fclose($pipes[1]);
				@fclose($pipes[2]);
				$exitCode = (int) proc_close($process);
				$combined = substr($out . "\n" . $err, 0, self::MAX_MAGICK_TEST_OUTPUT_BYTES);
				$outLines = array_merge($outLines, preg_split("/\r\n|\r|\n/", trim((string) $combined), -1, PREG_SPLIT_NO_EMPTY));
			}
		}

		if (empty($outLines) && $exitCode === 127) {
			$disableFunctions = array_map('trim', explode(',', (string) ini_get('disable_functions')));
			$execAvailable = !in_array('exec', $disableFunctions, true);

			if (!$execAvailable) {
				return new \WP_REST_Response(array('message' => 'proc_open unavailable and exec is disabled by PHP disable_functions.'), 400);
			}

			$cmd = escapeshellarg($path) . ' -version 2>&1';
			@exec($cmd, $outLines, $exitCode);
		}

		$output = trim(implode("\n", array_map('strval', $outLines)));
		if (strlen($output) > self::MAX_MAGICK_TEST_OUTPUT_BYTES) {
			$output = substr($output, 0, self::MAX_MAGICK_TEST_OUTPUT_BYTES);
		}

		if ('' === $output) {
			return rest_ensure_response(
				array(
					'code' => $exitCode,
					'output' => $output,
					'hint' => 'No output. If using ImageMagick 7, ensure the path points to `magick`.',
					'selected_path' => $path,
					'auto_selected' => $autoSelected,
					'define_strategy' => $strategy,
				)
			);
		}

		return rest_ensure_response(
			array(
				'code' => $exitCode,
				'output' => $output,
				'selected_path' => $path,
				'auto_selected' => $autoSelected,
				'define_strategy' => $strategy,
			)
		);
	}

	public function uploadTest(\WP_REST_Request $request): \WP_REST_Response
	{
		if (!function_exists('media_handle_sideload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$files = $request->get_file_params();
		$rawFile = isset($files['avif_local_support_test_file']) && is_array($files['avif_local_support_test_file'])
			? $files['avif_local_support_test_file']
			: array();

		if (empty($rawFile) || empty($rawFile['tmp_name'])) {
			return new \WP_REST_Response(array('message' => __('No file uploaded.', 'avif-local-support')), 400);
		}
		$uploadError = isset($rawFile['error']) ? (int) $rawFile['error'] : UPLOAD_ERR_NO_FILE;
		if (UPLOAD_ERR_OK !== $uploadError || !is_uploaded_file((string) $rawFile['tmp_name'])) {
			return new \WP_REST_Response(array('message' => __('Uploaded file is invalid.', 'avif-local-support')), 400);
		}

		$fileType = wp_check_filetype_and_ext(
			(string) $rawFile['tmp_name'],
			(string) ($rawFile['name'] ?? ''),
			array(
				'jpg' => 'image/jpeg',
				'jpeg' => 'image/jpeg',
			)
		);

		if (empty($fileType['ext']) || !\in_array($fileType['ext'], array('jpg', 'jpeg'), true)) {
			return new \WP_REST_Response(array('message' => __('Only JPEG files are allowed.', 'avif-local-support')), 400);
		}

		// Temporarily disable AVIF conversion during upload by removing the converter's hooks.
		// Conversions will happen incrementally via the async polling mechanism (uploadTestStatus),
		// preventing timeouts on large images that can take 30+ seconds per size to convert.
		\remove_filter('wp_update_attachment_metadata', array($this->converter, 'convertGeneratedSizes'), 20);
		\remove_filter('wp_handle_upload', array($this->converter, 'convertOriginalOnUpload'), 20);

		$attachment_id = media_handle_sideload($rawFile, 0);
		if (is_wp_error($attachment_id)) {
			// Re-add hooks before returning.
			\add_filter('wp_update_attachment_metadata', array($this->converter, 'convertGeneratedSizes'), 20, 2);
			\add_filter('wp_handle_upload', array($this->converter, 'convertOriginalOnUpload'), 20);
			return new \WP_REST_Response(array('message' => $attachment_id->get_error_message()), 400);
		}

		$file = get_attached_file($attachment_id);
		if ($file) {
			$metadata = \wp_generate_attachment_metadata($attachment_id, $file);
			if ($metadata) {
				\wp_update_attachment_metadata($attachment_id, $metadata);
			}
		}

		// Re-add the converter's hooks for normal operations.
		\add_filter('wp_update_attachment_metadata', array($this->converter, 'convertGeneratedSizes'), 20, 2);
		\add_filter('wp_handle_upload', array($this->converter, 'convertOriginalOnUpload'), 20);

		// Get sizes without converting - conversion happens incrementally via status endpoint.
		$sizes = $this->converter->getAttachmentSizes((int) $attachment_id);
		$editLink = get_edit_post_link($attachment_id);
		$title = get_the_title($attachment_id) ?: (string) $attachment_id;

		return rest_ensure_response(
			array(
				'attachment_id' => $attachment_id,
				'edit_link' => $editLink ?: '',
				'title' => $title,
				'sizes' => $sizes['sizes'] ?? array(),
				'complete' => false,
			)
		);
	}

	/**
	 * Poll for upload test status and convert one size at a time by index.
	 * Guaranteed to progress through the list one by one.
	 */
	public function uploadTestStatus(\WP_REST_Request $request): \WP_REST_Response
	{
		$attachmentId = (int) $request->get_param('attachment_id');
		$targetIndex = (int) $request->get_param('target_index');

		if ($attachmentId <= 0) {
			return new \WP_REST_Response(array('message' => 'Invalid attachment ID.'), 400);
		}
		if ($targetIndex < 0) {
			return new \WP_REST_Response(array('message' => 'Invalid target index.'), 400);
		}

		// Get current sizes.
		$data = $this->converter->getAttachmentSizes($attachmentId);
		$sizes = $data['sizes'] ?? array();
		$totalCount = count($sizes);

		// Fix stateless polling issue:
		// Function getAttachmentSizes() returns 'pending' if file is missing.
		// But if we have already iterated past an index (i < targetIndex),
		// and it is still 'pending' (meaning no file created), it corresponds to a failure.
		for ($i = 0; $i < $targetIndex && $i < $totalCount; $i++) {
			if (isset($sizes[$i]['status']) && 'pending' === $sizes[$i]['status']) {
				$sizes[$i]['status'] = 'failure';
			}
		}

		if ($targetIndex >= $totalCount) {
			// Index out of bounds - we are done.
			$complete = true;
		} else {
			$complete = false;
			$size = &$sizes[$targetIndex];

			if ('success' === $size['status']) {
				// Already converted, skip.
			} else {
				$jpegPath = $size['jpeg_path'] ?? '';
				$jpegPath = is_string($jpegPath) ? $jpegPath : '';
				$safeJpegPath = '' !== $jpegPath ? $this->normalizeUploadsPath($jpegPath, true) : null;
				if (null !== $safeJpegPath) {
					$conversionResult = $this->converter->convertSingleJpegToAvif($safeJpegPath);
					$success = $conversionResult->success;
					$size['converted'] = $success;
					$size['status'] = $success ? 'success' : 'failure';
					if (!$success && !empty($conversionResult->error)) {
						$size['error'] = $conversionResult->error;
					}
					if ($success) {
						// Refresh AVIF size.
						$avifPath = is_string($size['avif_path'] ?? null) ? (string) $size['avif_path'] : '';
						$safeAvifPath = '' !== $avifPath ? $this->normalizeUploadsPath($avifPath, true) : null;
						$size['avif_size'] = null !== $safeAvifPath ? (int) filesize($safeAvifPath) : 0;
					}
				} else {
					$size['status'] = 'failure';
				}
			}
		}

		// Mark the next item as 'processing' so frontend can show spinner.
		$nextIndex = $targetIndex + 1;
		if (!$complete && $nextIndex < $totalCount && 'pending' === $sizes[$nextIndex]['status']) {
			$sizes[$nextIndex]['status'] = 'processing';
		}

		$editLink = get_edit_post_link($attachmentId);
		$title = get_the_title($attachmentId) ?: (string) $attachmentId;

		return rest_ensure_response(
			array(
				'attachment_id' => $attachmentId,
				'edit_link' => $editLink ?: '',
				'title' => $title,
				'sizes' => $sizes,
				'complete' => $complete,
				'next_index' => $nextIndex,
			)
		);
	}

	public function playgroundCreate(\WP_REST_Request $request): \WP_REST_Response
	{
		if (!function_exists('wp_handle_sideload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if (!function_exists('image_make_intermediate_size')) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$files = $request->get_file_params();
		$rawFile = isset($files['avif_local_support_test_file']) && is_array($files['avif_local_support_test_file'])
			? $files['avif_local_support_test_file']
			: array();

		if (empty($rawFile) || empty($rawFile['tmp_name'])) {
			return new \WP_REST_Response(array('message' => __('No file uploaded.', 'avif-local-support')), 400);
		}
		if (!is_uploaded_file((string) $rawFile['tmp_name'])) {
			return new \WP_REST_Response(array('message' => __('Uploaded file is invalid.', 'avif-local-support')), 400);
		}

		$fileType = wp_check_filetype_and_ext(
			(string) $rawFile['tmp_name'],
			(string) ($rawFile['name'] ?? ''),
			array(
				'jpg' => 'image/jpeg',
				'jpeg' => 'image/jpeg',
			)
		);
		if (empty($fileType['ext']) || !\in_array($fileType['ext'], array('jpg', 'jpeg'), true)) {
			return new \WP_REST_Response(array('message' => __('Only JPEG files are allowed.', 'avif-local-support')), 400);
		}

		$upload = wp_handle_sideload(
			$rawFile,
			array(
				'test_form' => false,
				'mimes' => array(
					'jpg' => 'image/jpeg',
					'jpeg' => 'image/jpeg',
				),
			)
		);

		if (!is_array($upload) || !empty($upload['error']) || empty($upload['file'])) {
			$message = is_array($upload) ? (string) ($upload['error'] ?? '') : '';
			if ('' === $message) {
				$message = __('Failed to upload test JPEG.', 'avif-local-support');
			}
			return new \WP_REST_Response(array('message' => $message), 400);
		}

		$requestedSize = (string) $request->get_param('avif_local_support_playground_size');
		if ('' === $requestedSize) {
			$requestedSize = (string) $request->get_param('playground_size');
		}
		[$selectedSize, $selectedSizeConfig] = $this->resolvePlaygroundImageSize($requestedSize);

		$sourcePath = (string) $upload['file'];
		$previewData = $this->createPlaygroundPreviewJpeg($sourcePath, $selectedSizeConfig);
		if (is_wp_error($previewData)) {
			if (file_exists($sourcePath)) {
				wp_delete_file($sourcePath);
			}
			return new \WP_REST_Response(array('message' => $previewData->get_error_message()), 400);
		}

		$jpegPath = (string) ($previewData['path'] ?? '');
		if ('' === $jpegPath || !file_exists($jpegPath)) {
			if (file_exists($sourcePath)) {
				wp_delete_file($sourcePath);
			}
			return new \WP_REST_Response(array('message' => __('Unable to prepare test image.', 'avif-local-support')), 400);
		}

		if ($sourcePath !== $jpegPath && file_exists($sourcePath)) {
			wp_delete_file($sourcePath);
		}

		$avifPath = (string) preg_replace('/\.(jpe?g)$/i', '.avif', $jpegPath);
		$token = wp_generate_password(24, false, false);
		$state = array(
			'owner_id' => (int) get_current_user_id(),
			'jpeg_path' => $jpegPath,
			'avif_path' => $avifPath,
			'width' => (int) ($previewData['width'] ?? 0),
			'height' => (int) ($previewData['height'] ?? 0),
			'target_width' => (int) ($previewData['target_width'] ?? 0),
			'target_height' => (int) ($previewData['target_height'] ?? 0),
			'image_size' => $selectedSize,
			'jpeg_quality' => (int) ($previewData['jpeg_quality'] ?? 0),
			'jpeg_quality_source' => (string) ($previewData['jpeg_quality_source'] ?? ''),
		);

		$settings = $this->sanitizePlaygroundSettings(array());
		$result = $this->converter->convertJpegToAvifWithSettings(
			$jpegPath,
			$avifPath,
			$this->buildPlaygroundAvifSettings($settings)
		);
		$error = $result->success ? '' : (string) ($result->error ?? __('Conversion failed.', 'avif-local-support'));

		set_transient($this->getPlaygroundStateKey($token), $state, DAY_IN_SECONDS);

		return rest_ensure_response($this->buildPlaygroundResponse($token, $state, $settings, $error));
	}

	public function playgroundPreview(\WP_REST_Request $request): \WP_REST_Response
	{
		$token = (string) $request->get_param('token');
		if ('' === $token) {
			return new \WP_REST_Response(array('message' => __('Missing preview token.', 'avif-local-support')), 400);
		}

		$state = $this->getPlaygroundState($token);
		if (is_wp_error($state)) {
			return new \WP_REST_Response(array('message' => $state->get_error_message()), 400);
		}

		$payload = $request->get_json_params();
		$rawSettings = (is_array($payload) && isset($payload['settings']) && is_array($payload['settings']))
			? $payload['settings']
			: array();
		$settings = $this->sanitizePlaygroundSettings($rawSettings);

		$result = $this->converter->convertJpegToAvifWithSettings(
			(string) $state['jpeg_path'],
			(string) $state['avif_path'],
			$this->buildPlaygroundAvifSettings($settings)
		);
		$error = $result->success ? '' : (string) ($result->error ?? __('Conversion failed.', 'avif-local-support'));

		return rest_ensure_response($this->buildPlaygroundResponse($token, $state, $settings, $error));
	}

	public function playgroundApplySettings(\WP_REST_Request $request): \WP_REST_Response
	{
		$payload = $request->get_json_params();
		$rawSettings = (is_array($payload) && isset($payload['settings']) && is_array($payload['settings']))
			? $payload['settings']
			: array();
		$settings = $this->sanitizePlaygroundSettings($rawSettings);

		update_option('aviflosu_quality', $settings['quality']);
		update_option('aviflosu_speed', $settings['speed']);
		update_option('aviflosu_subsampling', $settings['subsampling']);
		update_option('aviflosu_bit_depth', $settings['bit_depth']);
		update_option('aviflosu_engine_mode', $settings['engine_mode']);

		return rest_ensure_response(
			array(
				'updated' => true,
				'settings' => $settings,
				'message' => __('AVIF settings updated.', 'avif-local-support'),
			)
		);
	}

	private function getPlaygroundStateKey(string $token): string
	{
		return 'aviflosu_playground_' . md5($token);
	}

	private function getPlaygroundState(string $token)
	{
		$state = get_transient($this->getPlaygroundStateKey($token));
		if (!is_array($state)) {
			return new \WP_Error('aviflosu_playground_expired', __('Playground session expired. Upload a JPEG again.', 'avif-local-support'));
		}

		$owner = (int) ($state['owner_id'] ?? 0);
		if ($owner > 0 && $owner !== (int) get_current_user_id()) {
			return new \WP_Error('aviflosu_playground_owner', __('This playground session belongs to a different user.', 'avif-local-support'));
		}

		$jpegPath = (string) ($state['jpeg_path'] ?? '');
		$safeJpegPath = '' !== $jpegPath ? $this->normalizeUploadsPath($jpegPath, true) : null;
		if (null === $safeJpegPath) {
			return new \WP_Error('aviflosu_playground_missing', __('Test JPEG is no longer available. Upload a new one.', 'avif-local-support'));
		}
		$state['jpeg_path'] = $safeJpegPath;

		if (empty($state['avif_path'])) {
			$state['avif_path'] = (string) preg_replace('/\.(jpe?g)$/i', '.avif', $safeJpegPath);
		}

		$avifPath = (string) ($state['avif_path'] ?? '');
		$safeAvifPath = '' !== $avifPath ? $this->normalizeUploadsPath($avifPath, file_exists($avifPath)) : null;
		if (null === $safeAvifPath) {
			return new \WP_Error('aviflosu_playground_missing', __('Test AVIF path is invalid. Upload a new JPEG.', 'avif-local-support'));
		}
		$state['avif_path'] = $safeAvifPath;

		return $state;
	}

	private function sanitizePlaygroundSettings(array $raw): array
	{
		$quality = isset($raw['quality']) ? (int) $raw['quality'] : (int) get_option('aviflosu_quality', 83);
		$speed = isset($raw['speed']) ? (int) $raw['speed'] : (int) get_option('aviflosu_speed', 0);
		$subsampling = isset($raw['subsampling']) ? (string) $raw['subsampling'] : (string) get_option('aviflosu_subsampling', '420');
		$bitDepth = isset($raw['bit_depth']) ? (string) $raw['bit_depth'] : (string) get_option('aviflosu_bit_depth', '8');
		$engineMode = isset($raw['engine_mode']) ? (string) $raw['engine_mode'] : (string) get_option('aviflosu_engine_mode', 'auto');

		$quality = max(0, min(100, $quality));
		$speed = max(0, min(8, $speed));

		if (!in_array($subsampling, array('420', '422', '444'), true)) {
			$subsampling = '420';
		}
		if (!in_array($bitDepth, array('8', '10', '12'), true)) {
			$bitDepth = '8';
		}
		if (!in_array($engineMode, array('auto', 'cli', 'imagick', 'gd'), true)) {
			$engineMode = 'auto';
		}

		return array(
			'quality' => $quality,
			'speed' => $speed,
			'subsampling' => $subsampling,
			'bit_depth' => $bitDepth,
			'engine_mode' => $engineMode,
		);
	}

	private function buildPlaygroundAvifSettings(array $settings): AvifSettings
	{
		$current = AvifSettings::fromOptions();
		$quality = (int) ($settings['quality'] ?? $current->quality);

		return new AvifSettings(
			quality: $quality,
			speed: (int) ($settings['speed'] ?? $current->speed),
			subsampling: (string) ($settings['subsampling'] ?? $current->subsampling),
			bitDepth: (string) ($settings['bit_depth'] ?? $current->bitDepth),
			engineMode: (string) ($settings['engine_mode'] ?? $current->engineMode),
			cliPath: $current->cliPath,
			disableMemoryCheck: $current->disableMemoryCheck,
			lossless: $quality >= 100,
			convertOnUpload: $current->convertOnUpload,
			convertViaSchedule: $current->convertViaSchedule,
			cliArgs: $current->cliArgs,
			cliEnv: $current->cliEnv,
			maxDimension: $current->maxDimension
		);
	}

	private function resolvePlaygroundImageSize(string $requestedSize): array
	{
		$sizes = $this->getRegisteredPlaygroundImageSizes();

		$defaultSize = '';
		foreach (array('large', 'medium_large', 'medium', 'thumbnail') as $preferredSize) {
			if (isset($sizes[$preferredSize])) {
				$defaultSize = $preferredSize;
				break;
			}
		}
		if ('' === $defaultSize) {
			$allSizeNames = array_keys($sizes);
			$defaultSize = (string) ($allSizeNames[0] ?? 'large');
		}

		$requestedSize = sanitize_key($requestedSize);
		if ('' !== $requestedSize && isset($sizes[$requestedSize])) {
			return array($requestedSize, $sizes[$requestedSize]);
		}

		$fallback = $sizes[$defaultSize] ?? array(
			'width' => 1024,
			'height' => 0,
			'crop' => false,
		);

		return array($defaultSize, $fallback);
	}

	private function getRegisteredPlaygroundImageSizes(): array
	{
		$sizes = array();
		$additionalSizes = function_exists('wp_get_additional_image_sizes') ? (array) wp_get_additional_image_sizes() : array();

		foreach ((array) get_intermediate_image_sizes() as $sizeNameRaw) {
			$sizeName = sanitize_key((string) $sizeNameRaw);
			if ('' === $sizeName) {
				continue;
			}

			$width = 0;
			$height = 0;
			$rawCrop = false;

			if (isset($additionalSizes[$sizeName]) && is_array($additionalSizes[$sizeName])) {
				$sizeData = $additionalSizes[$sizeName];
				$width = (int) ($sizeData['width'] ?? 0);
				$height = (int) ($sizeData['height'] ?? 0);
				$rawCrop = $sizeData['crop'] ?? false;
			} else {
				$width = (int) get_option("{$sizeName}_size_w", 0);
				$height = (int) get_option("{$sizeName}_size_h", 0);
				$rawCrop = 'thumbnail' === $sizeName
					? get_option('thumbnail_crop', false)
					: get_option("{$sizeName}_crop", false);
			}

			if ($width <= 0 && $height <= 0) {
				continue;
			}

			$sizes[$sizeName] = array(
				'width' => $width,
				'height' => $height,
				'crop' => $this->normalizePlaygroundCrop($rawCrop),
			);
		}

		if (empty($sizes)) {
			$sizes['large'] = array(
				'width' => 1024,
				'height' => 0,
				'crop' => false,
			);
		}

		return $sizes;
	}

	private function normalizePlaygroundCrop($rawCrop)
	{
		if (is_array($rawCrop)) {
			$horizontalRaw = isset($rawCrop[0]) ? sanitize_key((string) $rawCrop[0]) : 'center';
			$verticalRaw = isset($rawCrop[1]) ? sanitize_key((string) $rawCrop[1]) : 'center';
			$horizontal = in_array($horizontalRaw, array('left', 'center', 'right'), true) ? $horizontalRaw : 'center';
			$vertical = in_array($verticalRaw, array('top', 'center', 'bottom'), true) ? $verticalRaw : 'center';
			return array($horizontal, $vertical);
		}

		if (is_bool($rawCrop)) {
			return $rawCrop;
		}

		if (is_numeric($rawCrop)) {
			return (int) $rawCrop === 1;
		}

		if (is_string($rawCrop)) {
			$normalized = strtolower(trim($rawCrop));
			if ('' === $normalized || in_array($normalized, array('0', 'false', 'off', 'no'), true)) {
				return false;
			}
			if (in_array($normalized, array('1', 'true', 'on', 'yes'), true)) {
				return true;
			}
		}

		return (bool) $rawCrop;
	}

	private function shouldUseOriginalPlaygroundJpeg(int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight, $crop): bool
	{
		if ($sourceWidth <= 0 || $sourceHeight <= 0) {
			return false;
		}

		$cropEnabled = is_array($crop) || (bool) $crop;
		if ($cropEnabled) {
			if ($targetWidth > 0 && $sourceWidth < $targetWidth) {
				return true;
			}
			if ($targetHeight > 0 && $sourceHeight < $targetHeight) {
				return true;
			}
			return false;
		}

		if ($targetWidth > 0 && $sourceWidth > $targetWidth) {
			return false;
		}
		if ($targetHeight > 0 && $sourceHeight > $targetHeight) {
			return false;
		}
		return true;
	}

	private function createPlaygroundPreviewJpeg(string $sourcePath, array $sizeConfig)
	{
		if (!function_exists('wp_get_image_editor') || !function_exists('image_make_intermediate_size')) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$jpegQuality = $this->getWordPressJpegQuality();
		$targetWidth = max(0, (int) ($sizeConfig['width'] ?? 0));
		$targetHeight = max(0, (int) ($sizeConfig['height'] ?? 0));
		$crop = $sizeConfig['crop'] ?? false;

		$targetWidth = max(0, (int) apply_filters('aviflosu_playground_preview_width', $targetWidth));
		$targetHeight = max(0, (int) apply_filters('aviflosu_playground_preview_height', $targetHeight, $targetWidth));
		$crop = apply_filters('aviflosu_playground_preview_crop', $crop, $targetWidth, $targetHeight);
		if ($targetWidth <= 0 || $targetHeight <= 0) {
			$crop = false;
		}

		if ($targetWidth <= 0 && $targetHeight <= 0) {
			$targetWidth = 1024;
		}

		$previewPath = (string) preg_replace('/\.(jpe?g)$/i', '-playground.jpg', $sourcePath);
		if ('' === $previewPath) {
			return new \WP_Error('aviflosu_playground_resize', __('Unable to create a preview image path.', 'avif-local-support'));
		}

		$sourceSize = @getimagesize($sourcePath);
		$sourceWidth = (int) ($sourceSize[0] ?? 0);
		$sourceHeight = (int) ($sourceSize[1] ?? 0);

		if ($this->shouldUseOriginalPlaygroundJpeg($sourceWidth, $sourceHeight, $targetWidth, $targetHeight, $crop)) {
			if (!@copy($sourcePath, $previewPath)) {
				return new \WP_Error('aviflosu_playground_resize', __('Unable to create preview image.', 'avif-local-support'));
			}
			return array(
				'path' => $previewPath,
				'width' => $sourceWidth,
				'height' => $sourceHeight,
				'target_width' => $targetWidth,
				'target_height' => $targetHeight,
				'jpeg_quality' => 0,
				'jpeg_quality_source' => 'original',
			);
		}

		// Prefer the editor path for more reliable resizing from side-loaded images.
		$editor = wp_get_image_editor($sourcePath);
		if (!is_wp_error($editor)) {
			if (method_exists($editor, 'set_quality')) {
				$editor->set_quality($jpegQuality);
			}
			$resizeWidth = $targetWidth > 0 ? $targetWidth : null;
			$resizeHeight = $targetHeight > 0 ? $targetHeight : null;
			$resized = $editor->resize($resizeWidth, $resizeHeight, $crop);
			if (!is_wp_error($resized)) {
				$saved = $editor->save($previewPath, 'image/jpeg');
				if (is_array($saved) && !empty($saved['path'])) {
					return array(
						'path' => (string) $saved['path'],
						'width' => (int) ($saved['width'] ?? 0),
						'height' => (int) ($saved['height'] ?? 0),
						'target_width' => $targetWidth,
						'target_height' => $targetHeight,
						'jpeg_quality' => $jpegQuality,
						'jpeg_quality_source' => 'wp',
					);
				}
			}
		}

		$resized = image_make_intermediate_size($sourcePath, $targetWidth, $targetHeight, $crop);
		if (is_array($resized)) {
			$resizedPath = '';
			if (!empty($resized['path'])) {
				$resizedPath = (string) $resized['path'];
			} elseif (!empty($resized['file'])) {
				$resizedPath = trailingslashit((string) dirname($sourcePath)) . ltrim((string) $resized['file'], '/\\');
			}
				if ('' !== $resizedPath && file_exists($resizedPath)) {
					if ($resizedPath !== $previewPath && @copy($resizedPath, $previewPath)) {
						wp_delete_file($resizedPath);
						$resizedPath = $previewPath;
					}
				$resizedSize = @getimagesize($resizedPath);
				return array(
					'path' => $resizedPath,
					'width' => (int) ($resizedSize[0] ?? ($resized['width'] ?? 0)),
					'height' => (int) ($resizedSize[1] ?? ($resized['height'] ?? 0)),
					'target_width' => $targetWidth,
					'target_height' => $targetHeight,
					'jpeg_quality' => $jpegQuality,
					'jpeg_quality_source' => 'wp',
				);
			}
		}

		if (!@copy($sourcePath, $previewPath)) {
			return new \WP_Error('aviflosu_playground_resize', __('Unable to create a preview image.', 'avif-local-support'));
		}

		$size = @getimagesize($previewPath);
		return array(
			'path' => $previewPath,
			'width' => (int) ($size[0] ?? 0),
			'height' => (int) ($size[1] ?? 0),
			'target_width' => $targetWidth,
			'target_height' => $targetHeight,
			'jpeg_quality' => 0,
			'jpeg_quality_source' => 'original',
		);
	}

	private function getWordPressJpegQuality(): int
	{
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress hook.
		$quality = (int) apply_filters('wp_editor_set_quality', 82, 'image/jpeg');
		return max(1, min(100, $quality));
	}

	private function uploadPathToUrl(string $path): string
	{
		$uploads = wp_upload_dir();
		$baseDir = trailingslashit((string) ($uploads['basedir'] ?? ''));
		$baseUrl = trailingslashit((string) ($uploads['baseurl'] ?? ''));
		if ('' === $baseDir || '' === $baseUrl) {
			return '';
		}
		if (!str_starts_with($path, $baseDir)) {
			return '';
		}
		$relative = ltrim(substr($path, strlen($baseDir)), '/\\');
		$relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
		return $baseUrl . $relative;
	}

	private function buildPlaygroundResponse(string $token, array $state, array $settings, string $error = ''): array
	{
		$jpegPathRaw = (string) ($state['jpeg_path'] ?? '');
		$avifPathRaw = (string) ($state['avif_path'] ?? '');

		$jpegPath = '' !== $jpegPathRaw ? (string) ($this->normalizeUploadsPath($jpegPathRaw, true) ?? '') : '';
		$avifPath = '' !== $avifPathRaw ? (string) ($this->normalizeUploadsPath($avifPathRaw, true) ?? '') : '';

		// Ensure file size/mtime checks reflect the just-written preview artifacts.
		if ('' !== $jpegPath) {
			clearstatcache(true, $jpegPath);
		}
		if ('' !== $avifPath) {
			clearstatcache(true, $avifPath);
		}

		$jpegExists = '' !== $jpegPath && file_exists($jpegPath);
		$avifExists = '' !== $avifPath && file_exists($avifPath);

		$size = $jpegExists ? @getimagesize($jpegPath) : false;
		$jpegUrl = $jpegExists ? $this->uploadPathToUrl($jpegPath) : '';
		$avifUrl = $avifExists ? $this->uploadPathToUrl($avifPath) : '';
		$cacheBustVersion = $avifExists ? (string) ((int) filemtime($avifPath)) : (string) time();
		$cacheBustRevision = (string) wp_rand(1000, 999999);

		return array(
			'token' => $token,
			'settings' => $settings,
			'image_size' => (string) ($state['image_size'] ?? ''),
			'target_width' => (int) ($state['target_width'] ?? 0),
			'target_height' => (int) ($state['target_height'] ?? 0),
			'width' => (int) ($size[0] ?? ($state['width'] ?? 0)),
			'height' => (int) ($size[1] ?? ($state['height'] ?? 0)),
			'jpeg_url' => $jpegUrl,
			'jpeg_download_url' => $jpegUrl,
			'jpeg_name' => basename($jpegPath),
			'jpeg_size' => $jpegExists ? (int) filesize($jpegPath) : 0,
			'jpeg_quality' => (int) ($state['jpeg_quality'] ?? 0),
			'jpeg_quality_source' => (string) ($state['jpeg_quality_source'] ?? ''),
			'avif_url' => $avifUrl
				? add_query_arg(
					array(
						'v' => $cacheBustVersion,
						'r' => $cacheBustRevision,
					),
					$avifUrl
				)
				: '',
			'avif_download_url' => $avifUrl,
			'avif_name' => basename($avifPath),
			'avif_size' => $avifExists ? (int) filesize($avifPath) : 0,
			'success' => $avifExists && '' === $error,
			'error' => $error,
		);
	}

	/**
	 * Get ThumbHash statistics.
	 */
	public function thumbhashStats(\WP_REST_Request $request): \WP_REST_Response
	{
		return rest_ensure_response(\Ddegner\AvifLocalSupport\ThumbHash::getStats());
	}

	/**
	 * Generate ThumbHashes for all existing images.
	 */
	public function thumbhashGenerateAll(\WP_REST_Request $request): \WP_REST_Response
	{
		$result = \Ddegner\AvifLocalSupport\ThumbHash::generateAll();
		return rest_ensure_response($result);
	}

	/**
	 * Request stop for in-progress ThumbHash bulk generation.
	 */
	public function thumbhashStop(\WP_REST_Request $request): \WP_REST_Response
	{
		\Ddegner\AvifLocalSupport\ThumbHash::requestStop();
		return rest_ensure_response(array('stopped' => true));
	}

	/**
	 * Delete all ThumbHash metadata.
	 */
	public function thumbhashDeleteAll(\WP_REST_Request $request): \WP_REST_Response
	{
		$deleted = \Ddegner\AvifLocalSupport\ThumbHash::deleteAll();
		return rest_ensure_response(array('deleted' => $deleted));
	}
}
