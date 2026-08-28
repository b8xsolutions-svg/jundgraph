<?php
/**
 * ============================================================
 *  SANCHEZ GROWTH — Armazenamento de leads do Quiz (buffets)
 * ============================================================
 *
 *  O QUE FAZ:
 *   • Cria o menu "Leads Sanchez Growth" no painel do WordPress.
 *   • Recebe os dados do quiz (sanchez-growth-quiz.html) e grava
 *     cada resposta como um lead.
 *   • Mostra Nome, WhatsApp, Instagram, Faturamento e se deseja
 *     investir direto na lista do admin.
 *   • Envia um e-mail avisando a cada novo lead.
 *
 *  COMO INSTALAR (WPCode):
 *   • Crie um "Snippet de PHP", cole TODO este conteúdo
 *     (SEM a linha <?php da 1ª linha), deixe "Run Everywhere"
 *     (Auto Insert) e ative.
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
 *  CONFIG — para quem vai o aviso por e-mail de novo lead.
 *  Por padrão usa o e-mail de administrador do site.
 *  Para definir outro(s), troque a linha abaixo — separe vários
 *  por vírgula. Deixe '' para não enviar e-mail.
 * ============================================================ */
if ( ! defined( 'SANCHEZ_NOTIFY_EMAIL' ) ) {
	define( 'SANCHEZ_NOTIFY_EMAIL', get_option( 'admin_email' ) );
}

/* 1) Registra o tipo de conteúdo dos leads da Sanchez */
add_action( 'init', function () {
	register_post_type( 'sanchez_lead', array(
		'labels' => array(
			'name'          => 'Leads Sanchez Growth',
			'singular_name' => 'Lead Sanchez',
			'menu_name'     => 'Leads Sanchez Growth',
			'all_items'     => 'Todos os Leads',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-groups',
		'menu_position'   => 29,
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
	) );
} );

/* 2) Endpoint AJAX: recebe e grava o lead (logados e visitantes) */
add_action( 'wp_ajax_sanchez_save_lead',        'sanchez_save_lead' );
add_action( 'wp_ajax_nopriv_sanchez_save_lead', 'sanchez_save_lead' );

function sanchez_save_lead() {

	$nome        = isset( $_POST['nome'] )              ? sanitize_text_field( wp_unslash( $_POST['nome'] ) )              : '';
	$whatsapp    = isset( $_POST['whatsapp'] )          ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) )          : '';
	$instagram   = isset( $_POST['instagram'] )         ? sanitize_text_field( wp_unslash( $_POST['instagram'] ) )         : '';
	$faturamento = isset( $_POST['faturamento_faixa'] ) ? sanitize_text_field( wp_unslash( $_POST['faturamento_faixa'] ) ) : '';
	$investir    = isset( $_POST['deseja_investir'] )   ? sanitize_text_field( wp_unslash( $_POST['deseja_investir'] ) )   : '';
	$origem      = isset( $_POST['origem'] )            ? esc_url_raw( wp_unslash( $_POST['origem'] ) )                    : '';

	// Precisa ter ao menos nome ou whatsapp para gravar
	if ( '' === $nome && '' === $whatsapp ) {
		wp_send_json_error( array( 'msg' => 'dados insuficientes' ), 400 );
	}

	$instagram = ltrim( $instagram, '@' );

	$titulo = trim( $nome . ( $instagram ? ' — @' . $instagram : '' ) );
	if ( '' === $titulo ) { $titulo = 'Lead ' . current_time( 'd/m/Y H:i' ); }

	$post_id = wp_insert_post( array(
		'post_type'   => 'sanchez_lead',
		'post_status' => 'publish',
		'post_title'  => $titulo,
	), true );

	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'msg' => 'falha ao gravar' ), 500 );
	}

	update_post_meta( $post_id, 'sg_nome',        $nome );
	update_post_meta( $post_id, 'sg_whatsapp',    $whatsapp );
	update_post_meta( $post_id, 'sg_instagram',   $instagram );
	update_post_meta( $post_id, 'sg_faturamento', $faturamento );
	update_post_meta( $post_id, 'sg_investir',    $investir );
	update_post_meta( $post_id, 'sg_origem',      $origem );

	/* Notificação por e-mail */
	if ( SANCHEZ_NOTIFY_EMAIL ) {
		$assunto = 'Novo lead Sanchez Growth' . ( $nome ? ' — ' . $nome : '' );
		$corpo   = "Alguém preencheu o quiz de prospecção para buffets.\n\n";
		$corpo  .= 'Nome: '        . ( $nome        ? $nome            : '—' ) . "\n";
		$corpo  .= 'WhatsApp: '    . ( $whatsapp    ? $whatsapp        : '—' ) . "\n";
		$corpo  .= 'Instagram: '   . ( $instagram   ? '@' . $instagram : '—' ) . "\n";
		$corpo  .= 'Faturamento: ' . ( $faturamento ? $faturamento     : '—' ) . "\n";
		$corpo  .= 'Deseja investir: ' . ( $investir ? $investir       : '—' ) . "\n\n";
		$corpo  .= 'Ver o lead no painel: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . "\n";

		$destinatarios = array_filter( array_map( 'trim', explode( ',', SANCHEZ_NOTIFY_EMAIL ) ) );
		wp_mail( $destinatarios, $assunto, $corpo );
	}

	wp_send_json_success( array( 'id' => $post_id ) );
}

/* 3) Colunas na listagem do admin */
add_filter( 'manage_sanchez_lead_posts_columns', function ( $cols ) {
	return array(
		'cb'             => $cols['cb'],
		'title'          => 'Nome / Instagram',
		'sg_whatsapp'    => 'WhatsApp',
		'sg_faturamento' => 'Faturamento',
		'sg_investir'    => 'Deseja investir',
		'date'           => 'Recebido em',
	);
} );

add_action( 'manage_sanchez_lead_posts_custom_column', function ( $col, $post_id ) {
	if ( 'sg_whatsapp' === $col ) {
		$w = get_post_meta( $post_id, 'sg_whatsapp', true );
		$digits = preg_replace( '/\D/', '', $w );
		echo $w ? '<a href="https://wa.me/' . esc_attr( $digits ) . '" target="_blank">' . esc_html( $w ) . '</a>' : '—';
	}
	if ( 'sg_faturamento' === $col ) {
		echo esc_html( get_post_meta( $post_id, 'sg_faturamento', true ) ?: '—' );
	}
	if ( 'sg_investir' === $col ) {
		echo esc_html( get_post_meta( $post_id, 'sg_investir', true ) ?: '—' );
	}
}, 10, 2 );

/* 4) Detalhes completos ao abrir o lead */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'sanchez_lead_details', 'Dados do Lead', function ( $post ) {
		$fields = array(
			'sg_nome'        => 'Nome',
			'sg_whatsapp'    => 'WhatsApp',
			'sg_instagram'   => 'Instagram',
			'sg_faturamento' => 'Faturamento mensal',
			'sg_investir'    => 'Deseja investir',
			'sg_origem'      => 'Página de origem',
		);
		echo '<table class="widefat striped"><tbody>';
		foreach ( $fields as $key => $label ) {
			$val = get_post_meta( $post->ID, $key, true );
			if ( 'sg_instagram' === $key && $val ) { $val = '@' . $val; }
			echo '<tr><th style="width:220px">' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}, 'sanchez_lead', 'normal', 'high' );
} );
