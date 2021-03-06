<?php
/*
 * Plugin Name: Post Looper
 * Plugin URI: trepmal.com
 * Description:
 * Version:
 * Author: Kailey Lampert
 * Author URI: kaileylampert.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * TextDomain: post-looper
 * DomainPath:
 * Network:
 */

$post_looper = new Post_Looper();

class Post_Looper {

	/**
	 *
	 */
	function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_menu',            array( $this, 'menu' ) );
		add_action( 'wp_ajax_pl_loop',       array( $this, 'loop' ) );
	}

	/**
	 *
	 */
	function admin_enqueue_scripts( $hook ) {
		if ( 'settings_page_Post_Looper' != $hook ) {
			return;
		}
		wp_enqueue_script( 'codemirror', plugins_url( 'codemirror/codemirror.js', __FILE__ ), array(), 3.13 );
		wp_enqueue_script( 'codemirror-clike', plugins_url( 'codemirror/mode/clike.js', __FILE__ ), array('codemirror'), 3.13 );
		wp_enqueue_script( 'codemirror-css', plugins_url( 'codemirror/mode/css.js', __FILE__ ), array('codemirror'), 3.13 );
		wp_enqueue_script( 'codemirror-js', plugins_url( 'codemirror/mode/javascript.js', __FILE__ ), array('codemirror'), 3.13 );
		wp_enqueue_script( 'codemirror-php', plugins_url( 'codemirror/mode/php.js', __FILE__ ), array('codemirror'), 3.13 );
		wp_enqueue_style( 'codemirror', plugins_url( 'codemirror/codemirror.css', __FILE__ ) );
	}

	/**
	 *
	 */
	function menu() {
		add_options_page( __( 'Post Looper', 'post-looper' ), __( 'Post Looper', 'post-looper' ), 'manage_options', __CLASS__, array( $this, 'page' ) );
	}

	/**
	 *
	 */
	function page() {
		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		?><div class="wrap">
		<h2><?php _e( 'Post Looper', 'post-looper' ); ?></h2>
		<form>
		<?php
		$post_types = get_post_types( array( 'public' => true ) );
		echo '<p><label>'. __( 'Loop through:', 'post-looper' );
		if ( count( $post_types ) > 0 ) {
			echo '<select id="pl-post-type">';
			echo "<option value=''>--</option>";
			foreach ( $post_types as $_post_type ) {
				echo "<option value='$_post_type'>$_post_type</option>";
			}
			echo "<option value='any'>any</option>";
			echo '</select>';
		}
		echo '</label> ';

		$post_statuses = array_merge( get_post_statuses(), get_page_statuses() );
		echo '<label>';
		if ( count( $post_statuses ) > 0 ) {
			echo '<select id="pl-post-status">';
			echo "<option value=''>--</option>";
			foreach ( $post_statuses as $_post_status => $_post_status_label ) {
				$s = selected( $_post_status, 'publish', false );
				echo "<option value='$_post_status'$s>$_post_status_label</option>";
			}
			echo "<option value='any'>any</option>";
			echo '</select>';
		}
		echo '</label>';
		echo ' <label>'. __( 'Posts per loop:', 'post-looper' ) .'<input type="number" id="pl-ppl" class="small-text" value="10" /></label>';
		echo '</p>';
		?>
		<label for="pl-command"><?php _e( 'Command (runs inside the loop):', 'post-looper' ); ?></label>
		<textarea id="pl-command" class="large-text" rows="5">&lt;?php</textarea>
		<input type="hidden" id="pl-last-id" value="0" />
		<p><?php
			submit_button( __( 'Go', 'post-looper' ), 'primary', 'pl-submit', false );
			echo ' ';
			submit_button( __( 'Pause', 'post-looper' ), 'secondary', 'pl-pause', false );
			echo ' ';
			submit_button( __( 'Reset', 'post-looper' ), 'secondary', 'pl-reset', false );
		?></p>
		</form>
		<?php _e( 'Output:', 'post-looper' ); ?>
		<textarea id="pl-command-return" class="large-text" rows="2" readonly="readonly" style="background: #555;color: #f3f3f3;font-family: courier;font-size: 13px;"></textarea>
		<?php submit_button( __( 'Clear output log', 'post-looper' ), 'small', 'pl-clear' ); ?>
		</div><?php
	}

	/**
	 *
	 */
	function admin_footer() {
		?><script>
      var editor = CodeMirror.fromTextArea(document.getElementById("pl-command"), {
        lineNumbers: true,
        matchBrackets: true,
        mode: "application/x-httpd-php",
        indentUnit: 4,
        indentWithTabs: true,
        enterMode: "keep",
        tabMode: "shift"
      });

		jQuery(document).ready(function($){
			var $pl_submit = $('#pl-submit'),
				$pl_pause = $('#pl-pause'),
				$pl_reset = $('#pl-reset'),
				$pl_post_type = $('#pl-post-type'),
				$pl_post_status = $('#pl-post-status'),
				$pl_response = $('#pl-command-return'),
				// $pl_last = $('#pl-last-id'),
				$pl_clear = $('#pl-clear'),
				pl_status = {
					pause: false,
					last_id: 0
				},
				pl_ays = '<?php _e( 'Are you sure?', 'post-looper' ); ?>';

			var pl_reset = function() {
				pl_status.last_id = 0;
				$pl_submit.prop('disabled', '');
				$pl_response.val('');
				pl_pause();
			}
			var pl_pause = function() {
				pl_status.pause = true;
				$pl_pause.prop('disabled', 'disabled');
				$pl_submit.prop('disabled', '');
			}
			var pl_resume = function() {
				pl_status.pause = false;
				$pl_submit.prop('disabled', 'disabled');
				$pl_pause.prop('disabled', '');
			}

			$pl_pause.click( function(ev) {
				ev.preventDefault();
				if ( pl_status.pause ) {
					pl_status.pause = false;
					$pl_submit.click();
				} else {
					pl_pause();
				}
			});

			$pl_reset.click( function(ev) {
				ev.preventDefault();
				conf = confirm( pl_ays );
				if ( ! conf ) return;

				pl_reset();
			});

			$pl_submit.click( function(ev) {
				pl_resume();
				ev.preventDefault();

				$.post( ajaxurl, {
					action: 'pl_loop',
					post_type: $('#pl-post-type').val(),
					post_status: $('#pl-post-status').val(),
					posts_per_loop: $('#pl-ppl').val(),
					last_id: pl_status.last_id,
					// command: $('#pl-command').val(),
					command: editor.getValue(),
					nonce: '<?php echo wp_create_nonce('post-looper'); ?>'
				}, function( response ){
					$pl_response.val( $pl_response.val() + response.result);

					// increase textarea height
					rows = parseInt( $pl_response.attr('rows') );
					if ( rows < 20 ) {
						$pl_response.attr( 'rows', rows+1 );
					}
					// scroll to bottom of textarea
					$pl_response.scrollTop(
						$pl_response[0].scrollHeight - $pl_response.height()
					);
					// console.log( response );

					if ( response.next_post ) {
						pl_status.last_id = response.next_post;
						if ( ! pl_status.pause )
							$pl_submit.click();
					} else {
						pl_pause();
						$pl_submit.prop('disabled', 'disabled');
					}
				},'json');
			});

			$pl_clear.click( function(ev) {
				ev.preventDefault();
				$pl_response.val('');
			});

		});
		</script><?php
	}

	/**
	 *
	 */
	function loop() {
		if ( ! check_ajax_referer( 'post-looper', 'nonce', false ) ) {
			$this->json_die( false, __( 'Not allowed', 'post-looper' ) );
		}
		$post_type = $_POST['post_type'];
		if ( empty( $post_type ) ) {
			$this->json_die( false, __( 'No post type selected', 'post-looper' ) );
		}
		if ( ! post_type_exists( $post_type ) && $post_type != 'any' ) {
			$this->json_die( false, __( 'Invalid post type selected', 'post-looper' ) );
		}

		$post_status = esc_attr( $_POST['post_status'] );
		$last_id     = empty( $_POST['last_id'] ) ? 0 : $_POST['last_id'];
		$ppl         = intval( $_POST['posts_per_loop'] );

		// get next set of posts
		global $wpdb;
		$where_piece[] = $wpdb->prepare( 'p.ID > %d', $last_id );
		if ( $post_type != 'any' ) {
			$where_piece[] = $wpdb->prepare( 'p.post_type = %s', $post_type );
		}
		if ( $post_status != 'any' ) {
			$where_piece[] = $wpdb->prepare( 'p.post_status = %s', $post_status );
		}

		$wheres = implode( ' AND ', $where_piece );
		$where = "WHERE $wheres";

		$query = "SELECT p.id FROM $wpdb->posts AS p $where ORDER BY ID ASC LIMIT $ppl";

		$query_key = 'pl_next_post_' . md5($query);
		$result = wp_cache_get( $query_key, 'counts' );
		$result = false;

		if ( false === $result ) {
			$result = $wpdb->get_results( $query );
			if ( null === $result ) {
				$result = '';
			}

			wp_cache_set( $query_key, $result, 'counts');
		}

		if ( ! $result ) {
			$this->json_die( false, __( 'Could not find next post.', 'post-looper' ) );
		}
		//

		global $post;
		$command_result = '';
		foreach ( $result as $sql_row ) {

			$post = get_post( $sql_row->id );
			setup_postdata( $post );
			ob_start();
			echo '=================================='."\n";
			echo '['; the_ID(); echo '] '; the_title(); echo "\n";
			echo '----------------------------------'."\n";
			$command = $this->loop_command( $_POST['command'] );
			$command_result .= ob_get_clean() ."\n\n";
			wp_reset_postdata();
		}

		$this->json_die( $sql_row->id, $command_result );

	}

	/**
	 *
	 */
	function json_die( $next, $result ) {
		die( json_encode( array(
			'next_post' => $next,
			'result'    => $result,
		) ) );
	}

	/**
	 *
	 */
	function loop_command( $raw ) {
		$command = stripslashes( trim( $raw ) );
		if ( '<?php' == $command ) {
			return;
		}
		$command = str_replace( '<?php<?php', '<?php', "<?php$command" );
		// $command = htmlspecialchars_decode( $command );
		$command = str_replace( '<br />', "\n", $command );
		// this chunk from the debug bar
		// http://plugins.svn.wordpress.org/debug-bar-console/tags/0.3/class-debug-bar-console.php
			// Trim the data
			$command = '?>' . trim( $command );

			// Do we end the string in PHP?
			$open  = strrpos( $command, '<?php' );
			$close = strrpos( $command, '?>' );

			// If we're still in PHP, ensure we end with a semicolon.
			if ( $open > $close ) {
				$command = rtrim( $command, ';' ) . ';';
			}

		eval( $command );
	}

}