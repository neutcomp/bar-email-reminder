<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BER_Reminder_Shortcode {
	public static function init() {
		add_shortcode( 'bardienst', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$atts        = shortcode_atts( array( 'split' => 'false', 'dateformat' => 'long' ), $atts, 'bardienst' );
		$split       = 'true' === strtolower( (string) $atts['split'] );
		$date_format = 'short' === strtolower( (string) $atts['dateformat'] ) ? 'short' : 'long';
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
		$columns = $split ? array_chunk( $reminders, (int) ceil( count( $reminders ) / 2 ) ) : array( $reminders );

		ob_start();
		?>
		<style>
			.ber-bardienst-table { width: 100%; }
			.ber-bardienst-table th,
			.ber-bardienst-table td { padding: 10px 12px; }
			.ber-bardienst-table tbody tr { background-color: #fff; }
			.ber-bardienst-table tbody tr:nth-child(even) { background-color: #f0f0f0; }
			.ber-bardienst-table.ber-bardienst-table-split th,
			.ber-bardienst-table.ber-bardienst-table-split td { width: 25%; }
		</style>
		<table class="ber-bardienst-table<?php echo $split ? ' ber-bardienst-table-split' : ''; ?>">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Datum', 'bar-email-reminder' ); ?></th>
					<th><?php esc_html_e( 'Team', 'bar-email-reminder' ); ?></th>
					<?php if ( $split ) : ?>
						<th><?php esc_html_e( 'Datum', 'bar-email-reminder' ); ?></th>
						<th><?php esc_html_e( 'Team', 'bar-email-reminder' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $reminders ) : ?>
				<tr><td colspan="<?php echo $split ? '4' : '2'; ?>"><?php esc_html_e( 'Geen bardiensten gevonden.', 'bar-email-reminder' ); ?></td></tr>
			<?php elseif ( $split ) : ?>
				<?php foreach ( $columns[0] as $index => $reminder ) : ?>
					<?php $second = isset( $columns[1][ $index ] ) ? $columns[1][ $index ] : null; ?>
					<tr>
						<td><?php echo esc_html( self::format_date( $reminder['date_object'], $date_format ) ); ?></td>
						<td><?php echo esc_html( $reminder['name'] ); ?></td>
						<td><?php echo $second ? esc_html( self::format_date( $second['date_object'], $date_format ) ) : ''; ?></td>
						<td><?php echo $second ? esc_html( $second['name'] ) : ''; ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : foreach ( $reminders as $reminder ) : ?>
				<tr>
					<td><?php echo esc_html( self::format_date( $reminder['date_object'], $date_format ) ); ?></td>
					<td><?php echo esc_html( $reminder['name'] ); ?></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
		<?php

		return ob_get_clean();
	}

	private static function format_date( $date, $format ) {
		if ( 'short' === $format ) {
			return $date->format( 'd-m-Y' );
		}

		$weekdays = array( 'zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag' );
		$months   = array( 1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december' );
		$weekday  = ucfirst( $weekdays[ (int) $date->format( 'w' ) ] );
		$month    = $months[ (int) $date->format( 'n' ) ];

		return sprintf( '%s %s %s', $weekday, $date->format( 'j' ), $month );
	}
}