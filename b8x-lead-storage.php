<?php
/**
 * ============================================================
 *  B8X — Armazenamento de leads do Quiz "Método Agenda Elétrica"
 * ============================================================
 *
 *  O QUE FAZ:
 *   • Cria um tipo de conteúdo "Leads B8X" no painel do WordPress.
 *   • Recebe os dados enviados pelo quiz (b8x-quiz.html) e grava
 *     cada resposta como um lead.
 *   • Mostra Nome, WhatsApp, Empresa, Investimento e Faturamento
 *     estimado direto na lista do admin.
 *   • Envia um e-mail avisando cada vez que alguém preenche o
 *     formulário (configure o destinatário em B8X_NOTIFY_EMAIL abaixo).
 *
 *  COMO INSTALAR (escolha 1 das opções):
 *   A) Plugin "Code Snippets": crie um novo snippet, cole TODO
 *      este conteúdo (SEM a linha <?php da 1ª linha) e ative.
 *   B) Tema: cole no arquivo functions.php do seu tema FILHO.
 *   C) Como plugin: salve este arquivo em
 *      wp-content/plugins/b8x-leads/b8x-leads.php e ative em Plugins.
 *
 *  Depois de instalado, os leads aparecem no menu lateral:  "Leads B8X".
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
 *  CONFIG — para quem vai o aviso por e-mail de novo lead.
 *  Troque pelo(s) e-mail(s) desejado(s). Para mais de um,
 *  separe por vírgula: 'a@x.com, b@y.com'.
 *  Deixe vazio ('') para NÃO enviar e-mail.
 * ============================================================ */
if ( ! defined( 'B8X_NOTIFY_EMAIL' ) ) {
	define( 'B8X_NOTIFY_EMAIL', 'b8xsolutions@gmail.com' );
}

/* 1) Registra o tipo de conteúdo (Custom Post Type) dos leads */
add_action( 'init', function () {
	register_post_type( 'b8x_lead', array(
		'labels' => array(
			'name'          => 'Leads B8X',
			'singular_name' => 'Lead B8X',
			'menu_name'     => 'Leads B8X',
			'all_items'     => 'Todos os Leads',
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-groups',
		'menu_position'=> 26,
		'supports'     => array( 'title' ),
		'capability_type' => 'post',
	) );
} );

/* 2) Endpoint AJAX: recebe e grava o lead (usuários logados e visitantes) */
add_action( 'wp_ajax_b8x_save_lead',        'b8x_save_lead' );
add_action( 'wp_ajax_nopriv_b8x_save_lead', 'b8x_save_lead' );

function b8x_save_lead() {

	$nome     = isset( $_POST['nome'] )     ? sanitize_text_field( wp_unslash( $_POST['nome'] ) )     : '';
	$whatsapp = isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '';
	$empresa  = isset( $_POST['empresa'] )  ? sanitize_text_field( wp_unslash( $_POST['empresa'] ) )  : '';
	$origem   = isset( $_POST['origem'] )   ? esc_url_raw( wp_unslash( $_POST['origem'] ) )            : '';

	$investimento = isset( $_POST['investimento'] )       ? intval( $_POST['investimento'] )       : 0;
	$leads        = isset( $_POST['leads'] )              ? intval( $_POST['leads'] )              : 0;
	$servicos     = isset( $_POST['servicos'] )           ? intval( $_POST['servicos'] )           : 0;
	$faturamento  = isset( $_POST['faturamento'] )        ? intval( $_POST['faturamento'] )        : 0;
	$servico_b8x  = isset( $_POST['servico_b8x'] )        ? intval( $_POST['servico_b8x'] )        : 0;
	$invest_total = isset( $_POST['investimento_total'] ) ? intval( $_POST['investimento_total'] ) : 0;

	// Precisa ter ao menos nome ou whatsapp para gravar
	if ( '' === $nome && '' === $whatsapp ) {
		wp_send_json_error( array( 'msg' => 'dados insuficientes' ), 400 );
	}

	$titulo = trim( $nome . ( $empresa ? ' — ' . $empresa : '' ) );
	if ( '' === $titulo ) { $titulo = 'Lead ' . current_time( 'd/m/Y H:i' ); }

	$post_id = wp_insert_post( array(
		'post_type'   => 'b8x_lead',
		'post_status' => 'publish',
		'post_title'  => $titulo,
	), true );

	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'msg' => 'falha ao gravar' ), 500 );
	}

	update_post_meta( $post_id, 'b8x_nome',         $nome );
	update_post_meta( $post_id, 'b8x_whatsapp',     $whatsapp );
	update_post_meta( $post_id, 'b8x_empresa',      $empresa );
	update_post_meta( $post_id, 'b8x_investimento',       $investimento );
	update_post_meta( $post_id, 'b8x_leads',              $leads );
	update_post_meta( $post_id, 'b8x_servicos',           $servicos );
	update_post_meta( $post_id, 'b8x_faturamento',        $faturamento );
	update_post_meta( $post_id, 'b8x_servico_b8x',        $servico_b8x );
	update_post_meta( $post_id, 'b8x_investimento_total', $invest_total );
	update_post_meta( $post_id, 'b8x_origem',             $origem );

	/* Notificação por e-mail: avisa que alguém preencheu o formulário */
	if ( B8X_NOTIFY_EMAIL ) {
		$assunto = 'Novo lead do Quiz B8X' . ( $nome ? ' — ' . $nome : '' );
		$corpo   = "Alguém preencheu o formulário do Método Agenda Elétrica.\n\n";
		$corpo  .= 'Nome: '     . ( $nome     ? $nome     : '—' ) . "\n";
		$corpo  .= 'WhatsApp: ' . ( $whatsapp ? $whatsapp : '—' ) . "\n";
		$corpo  .= 'Empresa: '  . ( $empresa  ? $empresa  : '—' ) . "\n";
		$corpo  .= 'Investimento pretendido: R$ ' . number_format_i18n( $investimento ) . "/mês\n\n";
		$corpo  .= 'Ver o lead completo no painel: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . "\n";

		$destinatarios = array_filter( array_map( 'trim', explode( ',', B8X_NOTIFY_EMAIL ) ) );
		wp_mail( $destinatarios, $assunto, $corpo );
	}

	wp_send_json_success( array( 'id' => $post_id ) );
}

/* 3) Colunas na listagem do admin */
add_filter( 'manage_b8x_lead_posts_columns', function ( $cols ) {
	return array(
		'cb'            => $cols['cb'],
		'title'         => 'Nome / Empresa',
		'b8x_whatsapp'  => 'WhatsApp',
		'b8x_empresa'   => 'Empresa',
		'b8x_investimento' => 'Investimento',
		'b8x_faturamento'  => 'Faturamento est.',
		'date'          => 'Recebido em',
	);
} );

add_action( 'manage_b8x_lead_posts_custom_column', function ( $col, $post_id ) {
	if ( 'b8x_whatsapp' === $col ) {
		$w = get_post_meta( $post_id, 'b8x_whatsapp', true );
		$digits = preg_replace( '/\D/', '', $w );
		echo $w ? '<a href="https://wa.me/55' . esc_attr( ltrim( $digits, '55' ) ) . '" target="_blank">' . esc_html( $w ) . '</a>' : '—';
	}
	if ( 'b8x_empresa' === $col ) {
		echo esc_html( get_post_meta( $post_id, 'b8x_empresa', true ) ?: '—' );
	}
	if ( 'b8x_investimento' === $col ) {
		$v = (int) get_post_meta( $post_id, 'b8x_investimento', true );
		echo $v ? 'R$ ' . number_format_i18n( $v ) : '—';
	}
	if ( 'b8x_faturamento' === $col ) {
		$v = (int) get_post_meta( $post_id, 'b8x_faturamento', true );
		echo $v ? 'R$ ' . number_format_i18n( $v ) : '—';
	}
}, 10, 2 );

/* 4) Detalhes completos ao abrir o lead (caixa no editor) */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'b8x_lead_details', 'Dados do Lead', function ( $post ) {
		$fields = array(
			'b8x_nome'         => 'Nome',
			'b8x_whatsapp'     => 'WhatsApp',
			'b8x_empresa'      => 'Empresa de elétrica',
			'b8x_investimento'       => 'Investimento em Ads (R$)',
			'b8x_servico_b8x'        => 'Serviço B8X (R$)',
			'b8x_investimento_total' => 'Investimento total (R$)',
			'b8x_leads'              => 'Leads estimados',
			'b8x_servicos'           => 'Vendas estimadas',
			'b8x_faturamento'        => 'Faturamento estimado (R$)',
			'b8x_origem'             => 'Página de origem',
		);
		echo '<table class="widefat striped"><tbody>';
		foreach ( $fields as $key => $label ) {
			$val = get_post_meta( $post->ID, $key, true );
			echo '<tr><th style="width:220px">' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}, 'b8x_lead', 'normal', 'high' );
} );
