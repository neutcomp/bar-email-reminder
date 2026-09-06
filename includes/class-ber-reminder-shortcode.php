<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BER_Reminder_Shortcode {
	public static function init() {
		add_shortcode( 'bardienst', array( __CLASS__, 'render' ) );
	}

	public static function render() {
		$reminder_ids = get_posts(
			array(
				'post_type'      => BER_Reminder_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$reminders = array();

		foreach ( $reminder_ids as $reminder_id ) {
			$reminder = BER_Reminder_Post_Type::get( $reminder_id );
			$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', $reminder['date'], wp_timezone() );

			if ( ! $date || $date->format( 'Y-m-d' ) !== $reminder['date'] ) {
				continue;
			}

			$reminder['date_object'] = $date;
			$reminders[]             = $reminder;
		}

		usort(
			$reminders,
			static function ( $first, $second ) {
				$date_comparison = $first['date_object']->getTimestamp() <=> $second['date_object']->getTimestamp();

				return $date_comparison ?: strcasecmp( $first['name'], $second['name'] );
			}
		);

		ob_start();
		?>
		<table class="ber-bardienst-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Datum', 'bar-email-reminder' ); ?></th>
					<th><?php esc_html_e( 'Naam', 'bar-email-reminder' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $reminders ) : ?>
				<tr><td colspan="2"><?php esc_html_e( 'Geen bardiensten gevonden.', 'bar-email-reminder' ); ?></td></tr>
			<?php else : foreach ( $reminders as $reminder ) : ?>
				<tr>
					<td><?php echo esc_html( self::format_date( $reminder['date_object'] ) ); ?></td>
					<td><?php echo esc_html( $reminder['name'] ); ?></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
		<?php

		return ob_get_clean();
	}

	private static function format_date( $date ) {
		$weekdays = array( 'zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag' );
		$months   = array( 1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december' );
		$weekday  = ucfirst( $weekdays[ (int) $date->format( 'w' ) ] );
		$month    = $months[ (int) $date->format( 'n' ) ];

		return sprintf( '%s %s %s', $weekday, $date->format( 'j' ), $month );
	}
}