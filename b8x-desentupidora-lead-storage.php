<?php
/**
 * ============================================================
 *  B8X — Armazenamento de leads do Quiz DESENTUPIDORA/DEDETIZADORA
 * ============================================================
 *
 *  O QUE FAZ:
 *   • Cria o menu "Leads Desentupidora B8X" no painel do WordPress.
 *   • Recebe os dados do quiz (b8x-desentupidora-quiz.html) e grava
 *     cada resposta como um lead.
 *   • Mostra Nome, WhatsApp, Instagram, Faturamento e se deseja
 *     investir direto na lista do admin.
 *   • Envia um e-mail avisando a cada novo lead.
 *
 *  COMO INSTALAR (WPCode):
 *   • Crie um "Snippet de PHP", cole TODO este conteúdo
 *     (SEM a linha <?php da 1ª linha), deixe "Run Everywhere"
 *     (Auto Insert) e ative.
 *
 *  Observação: independente dos outros snippets (elétrica e funerário)
 *  — pode ter todos ativos ao mesmo tempo, cada um com seu menu.
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
 *  CONFIG — para quem vai o aviso por e-mail de novo lead.
 *  Separe vários por vírgula. Deixe '' para não enviar e-mail.
 * ============================================================ */
if ( ! defined( 'B8X_DES_NOTIFY_EMAIL' ) ) {
	define( 'B8X_DES_NOTIFY_EMAIL', 'blaneckpaloma@gmail.com, leandro@b8x.com.br' );
}

/* 1) Registra o tipo de conteúdo dos leads da desentupidora */
add_action( 'init', function () {
	register_post_type( 'b8x_lead_des', array(
		'labels' => array(
			'name'          => 'Leads Desentupidora B8X',
			'singular_name' => 'Lead Desentupidora',
			'menu_name'     => 'Leads Desentupidora B8X',
			'all_items'     => 'Todos os Leads',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-groups',
		'menu_position'   => 28,
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
	) );
} );

/* 2) Endpoint AJAX: recebe e grava o lead (logados e visitantes) */
add_action( 'wp_ajax_b8x_save_lead_des',        'b8x_save_lead_des' );
add_action( 'wp_ajax_nopriv_b8x_save_lead_des', 'b8x_save_lead_des' );

function b8x_save_lead_des() {

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
		'post_type'   => 'b8x_lead_des',
		'post_status' => 'publish',
		'post_title'  => $titulo,
	), true );

	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'msg' => 'falha ao gravar' ), 500 );
	}

	update_post_meta( $post_id, 'b8x_nome',        $nome );
	update_post_meta( $post_id, 'b8x_whatsapp',    $whatsapp );
	update_post_meta( $post_id, 'b8x_instagram',   $instagram );
	update_post_meta( $post_id, 'b8x_faturamento', $faturamento );
	update_post_meta( $post_id, 'b8x_investir',    $investir );
	update_post_meta( $post_id, 'b8x_origem',      $origem );

	/* Notificação por e-mail */
	if ( B8X_DES_NOTIFY_EMAIL ) {
		$assunto = 'Novo lead Desentupidora B8X' . ( $nome ? ' — ' . $nome : '' );
		$corpo   = "Alguém preencheu o quiz de desentupidora/dedetizadora.\n\n";
		$corpo  .= 'Nome: '        . ( $nome        ? $nome                : '—' ) . "\n";
		$corpo  .= 'WhatsApp: '    . ( $whatsapp    ? $whatsapp            : '—' ) . "\n";
		$corpo  .= 'Instagram: '   . ( $instagram   ? '@' . $instagram     : '—' ) . "\n";
		$corpo  .= 'Faturamento: ' . ( $faturamento ? $faturamento         : '—' ) . "\n";
		$corpo  .= 'Deseja investir: ' . ( $investir ? $investir           : '—' ) . "\n\n";
		$corpo  .= 'Ver o lead no painel: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . "\n";

		$destinatarios = array_filter( array_map( 'trim', explode( ',', B8X_DES_NOTIFY_EMAIL ) ) );
		wp_mail( $destinatarios, $assunto, $corpo );
	}

	wp_send_json_success( array( 'id' => $post_id ) );
}

/* 3) Colunas na listagem do admin */
add_filter( 'manage_b8x_lead_des_posts_columns', function ( $cols ) {
	return array(
		'cb'              => $cols['cb'],
		'title'           => 'Nome / Instagram',
		'b8x_whatsapp'    => 'WhatsApp',
		'b8x_faturamento' => 'Faturamento',
		'b8x_investir'    => 'Deseja investir',
		'date'            => 'Recebido em',
	);
} );

add_action( 'manage_b8x_lead_des_posts_custom_column', function ( $col, $post_id ) {
	if ( 'b8x_whatsapp' === $col ) {
		$w = get_post_meta( $post_id, 'b8x_whatsapp', true );
		$digits = preg_replace( '/\D/', '', $w );
		echo $w ? '<a href="https://wa.me/' . esc_attr( $digits ) . '" target="_blank">' . esc_html( $w ) . '</a>' : '—';
	}
	if ( 'b8x_faturamento' === $col ) {
		echo esc_html( get_post_meta( $post_id, 'b8x_faturamento', true ) ?: '—' );
	}
	if ( 'b8x_investir' === $col ) {
		echo esc_html( get_post_meta( $post_id, 'b8x_investir', true ) ?: '—' );
	}
}, 10, 2 );

/* 4) Detalhes completos ao abrir o lead */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'b8x_lead_des_details', 'Dados do Lead', function ( $post ) {
		$fields = array(
			'b8x_nome'        => 'Nome',
			'b8x_whatsapp'    => 'WhatsApp',
			'b8x_instagram'   => 'Instagram',
			'b8x_faturamento' => 'Faturamento mensal',
			'b8x_investir'    => 'Deseja investir',
			'b8x_origem'      => 'Página de origem',
		);
		echo '<table class="widefat striped"><tbody>';
		foreach ( $fields as $key => $label ) {
			$val = get_post_meta( $post->ID, $key, true );
			if ( 'b8x_instagram' === $key && $val ) { $val = '@' . $val; }
			echo '<tr><th style="width:220px">' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}, 'b8x_lead_des', 'normal', 'high' );
} );
