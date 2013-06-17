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

	var $textdomain = 'post-looper';

	function __construct() {
		add_action( 'admin_menu', array( &$this, 'menu' ) );
		add_action( 'wp_ajax_pl_loop', array( &$this, 'loop' ) );
	}

	function menu() {
		add_options_page( __( 'Post Looper', $this->textdomain ), __( 'Post Looper', $this->textdomain ), 'manage_options', __CLASS__, array( &$this, 'page' ) );
	}

	function page() {
		add_action( 'admin_footer', array( &$this, 'admin_footer' ) );
		?><div class="wrap">
		<h2><?php _e( 'Post Looper', $this->textdomain ); ?></h2>
		<form>
		<?php
		$post_types = get_post_types( array( 'public' => true ) );
		echo '<p><label>'. __( 'Loop through:', $this->textdomain );
		if ( count( $post_types ) > 0 ) {
			echo '<select id="pl-post-type">';
			echo "<option value=''>--</option>";
			foreach( $post_types as $_post_type ) {
				echo "<option value='$_post_type'>$_post_type</option>";
			}
			echo "<option value='any'>any</option>";
			// echo "<option value='asdf'>asdf</option>";
			echo '</select>';
		}
		echo '</label> ';

		$post_statuses = array_merge( get_post_statuses(), get_page_statuses() );
		echo '<label>';
		if ( count( $post_statuses ) > 0 ) {
			echo '<select id="pl-post-status">';
			echo "<option value=''>--</option>";
			foreach( $post_statuses as $_post_status => $_post_status_label ) {
				$s = selected( $_post_status, 'publish', false );
				echo "<option value='$_post_status'$s>$_post_status_label</option>";
			}
			echo "<option value='any'>any</option>";
			// echo "<option value='asdf'>asdf</option>";
			echo '</select>';
		}
		echo '</label></p>';
		?>
		<label for="pl-command"><?php _e( 'Command (runs inside the loop):', $this->textdomain ); ?></label>
		<textarea id="pl-command" class="large-text" rows="5">&lt;?php</textarea>
		<input type="hidden" id="pl-last-id" value="0" />
		<p><?php
			submit_button( __( 'Go', $this->textdomain ), 'primary', 'pl-submit', false );
			echo ' ';
			submit_button( __( 'Pause', $this->textdomain ), 'secondary', 'pl-pause', false );
			echo ' ';
			submit_button( __( 'Reset', $this->textdomain ), 'secondary', 'pl-reset', false );
			// submit_button( __( 'Go', $this->textdomain ), 'primary', 'pl-submit' );
		?></p>
		</form>
		<?php _e( 'Output:', $this->textdomain ); ?>
		<textarea id="pl-command-return" class="large-text" rows="2" readonly="readonly" style="background: #555;color: #f3f3f3;font-family: courier;font-size: 13px;"></textarea>
		<?php submit_button( __( 'Clear', $this->textdomain ), 'small', 'pl-clear' ); ?>
		</div><?php
	}

	function admin_footer() {
		?><script>
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
				pl_ays = '<?php _e( 'Are you sure?', $this->textdomain ); ?>';

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
					last_id: pl_status.last_id,
					command: $('#pl-command').val(),
					nonce: '<?php echo wp_create_nonce('post-looper'); ?>'
				}, function( response ){
					$pl_response.val( $pl_response.val() + response.result + "\n");

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

	function loop() {
		if ( ! check_ajax_referer( 'post-looper', 'nonce', false ) )
			$this->json_die( false, __( 'Not allowed', $this->textdomain ) );
		$post_type = $_POST['post_type'];
		if ( empty( $post_type ) ) $this->json_die( false, __( 'No post type selected', $this->textdomain ) );
		if ( ! post_type_exists( $post_type ) && $post_type != 'any' ) $this->json_die( false, __( 'Invalid post type selected', $this->textdomain ) );

		$post_status = esc_attr( $_POST['post_status'] );
		$last_id = empty( $_POST['last_id'] ) ? 0 : $_POST['last_id'];


global $wpdb;
// $where = $wpdb->prepare( "WHERE p.ID > %d AND p.post_type = %s AND p.post_status = 'publish'", $last_id, $post_type );

$where_piece[] = $wpdb->prepare( 'p.ID > %d', $last_id );
if ( $post_type != 'any' )
	$where_piece[] = $wpdb->prepare( 'p.post_type = %s', $post_type );
if ( $post_status != 'any' )
	$where_piece[] = $wpdb->prepare( 'p.post_status = %s', $post_status );

$wheres = implode( ' AND ', $where_piece );
$where = "WHERE $wheres";

$query = "SELECT p.id FROM $wpdb->posts AS p $where ORDER BY ID ASC LIMIT 1";

$query_key = 'pl_next_post_' . md5($query);
$result = wp_cache_get($query_key, 'counts');
$result = false;

if ( false === $result ) {
	$result = $wpdb->get_results( $query );
	if ( null === $result )
		$result = '';

	wp_cache_set( $query_key, $result, 'counts');
}

if ( ! $result )
	$this->json_die( false, __( 'Could not find next post.', $this->textdomain ) );

$this_post_ = array_shift( $result );

		global $post;
		$post = get_post( $this_post_->id );
		setup_postdata( $post );
		ob_start();
		echo '----------------------------------'."\n";
		echo '['; the_ID(); echo '] '; the_title(); echo "\n";
		echo '----------------------------------'."\n";
		$command = $this->loop_command( $_POST['command'] );
		$command_result = ob_get_clean();
		wp_reset_postdata();

		$this->json_die( $this_post_->id, $command_result );

	}

	function json_die( $next, $result ) {
		die( json_encode( array(
			'next_post' => $next,
			'result' => $result,
		) ) );
	}

	function loop_command( $raw ) {
		$command = stripslashes( trim( $raw ) );
		if ( '<?php' == $command ) return;
		$command = str_replace( '<?php<?php', '<?php', "<?php$command" );
		// this chunk from the debug bar
		// http://plugins.svn.wordpress.org/debug-bar-console/tags/0.3/class-debug-bar-console.php
			// Trim the data
			$command = '?>' . trim( $command );

			// Do we end the string in PHP?
			$open  = strrpos( $command, '<?php' );
			$close = strrpos( $command, '?>' );

			// If we're still in PHP, ensure we end with a semicolon.
			if ( $open > $close )
				$command = rtrim( $command, ';' ) . ';';

		eval( $command );
	}

}