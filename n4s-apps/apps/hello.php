<?php
/**
 * App Name: Hello
 * Version: 1.1.0
 *
 * Beispiel-App / example app: PHP (Serverzeit per REST) zusammen mit JavaScript, zweisprachig DE/EN.
 * Einbinden / embed: [n4s_app name="hello"]
 * Als Vorlage kopieren / copy as template: Dateikopf (App Name, Version), n4s_register_app(), n4s_t() für Texte.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'n4s-apps/v1',
			'/hello',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true', // öffentlich; für private Apps current_user_can() prüfen.
				'callback'            => function () {
					return array( 'time' => wp_date( 'd.m.Y H:i:s' ) );
				},
			)
		);
	}
);

n4s_register_app(
	'hello',
	function () {
		wp_register_script( 'n4s-app-hello', '', array(), '1.1.0', true );
		wp_enqueue_script( 'n4s-app-hello' );
		wp_add_inline_script(
			'n4s-app-hello',
			'document.querySelectorAll(".n4s-hello").forEach(function(box){' .
			'var out=box.querySelector(".n4s-hello-out");' .
			'box.querySelector("button").addEventListener("click",function(){' .
			'fetch(box.dataset.url).then(function(r){return r.json();})' .
			'.then(function(d){out.textContent=box.dataset.label+" "+d.time;})' .
			'.catch(function(){out.textContent=box.dataset.error;});});});'
		);

		return '<div class="n4s-hello" data-url="' . esc_url( rest_url( 'n4s-apps/v1/hello' ) ) . '"'
			. ' data-label="' . esc_attr( n4s_t( 'Serverzeit:', 'Server time:' ) ) . '"'
			. ' data-error="' . esc_attr( n4s_t( 'Fehler beim Laden.', 'Error while loading.' ) ) . '">'
			. '<button type="button">' . esc_html( n4s_t( 'Serverzeit abrufen', 'Get server time' ) ) . '</button> '
			. '<span class="n4s-hello-out"></span></div>';
	}
);
