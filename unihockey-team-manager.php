<?php
/**
 * Plugin Name: Unihockey Team Manager
 * Description: Plugin zur Verwaltung von Unihockey Spielern, Spielen und einer Scorerliste.
 * Version: 1.0.0
 * Author: Jules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Hier werden später die CPTs, Meta Boxen und Shortcodes hinzugefügt.

/**
 * Register Custom Post Types for Spieler and Spiel.
 */
function uhm_register_cpts() {
    // CPT: Spieler
    $spieler_labels = array(
        'name'               => 'Spieler',
        'singular_name'      => 'Spieler',
        'add_new'            => 'Spieler hinzufügen',
        'add_new_item'       => 'Neuen Spieler hinzufügen',
        'edit_item'          => 'Spieler bearbeiten',
        'new_item'           => 'Neuer Spieler',
        'view_item'          => 'Spieler ansehen',
        'search_items'       => 'Spieler suchen',
        'not_found'          => 'Keine Spieler gefunden',
        'not_found_in_trash' => 'Keine Spieler im Papierkorb gefunden'
    );

    $spieler_args = array(
        'labels'             => $spieler_labels,
        'public'             => true,
        'has_archive'        => true,
        'publicly_queryable' => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'spieler'),
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-groups',
        'supports'           => array('title', 'thumbnail'), // Name und evtl. Foto
    );

    register_post_type('uhm_spieler', $spieler_args);

    // CPT: Spiel
    $spiel_labels = array(
        'name'               => 'Spiele',
        'singular_name'      => 'Spiel',
        'add_new'            => 'Spiel hinzufügen',
        'add_new_item'       => 'Neues Spiel hinzufügen',
        'edit_item'          => 'Spiel bearbeiten',
        'new_item'           => 'Neues Spiel',
        'view_item'          => 'Spiel ansehen',
        'search_items'       => 'Spiele suchen',
        'not_found'          => 'Keine Spiele gefunden',
        'not_found_in_trash' => 'Keine Spiele im Papierkorb gefunden'
    );

    $spiel_args = array(
        'labels'             => $spiel_labels,
        'public'             => true,
        'has_archive'        => true,
        'publicly_queryable' => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'spiel'),
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'menu_position'      => 21,
        'menu_icon'          => 'dashicons-calendar-alt',
        'supports'           => array('title', 'editor', 'thumbnail'), // Titel (z.B. Team A vs Team B), Spielbericht (editor)
    );

    register_post_type('uhm_spiel', $spiel_args);
}
add_action('init', 'uhm_register_cpts');

/**
 * Meta Box for Game Stats (Scorers)
 */
function uhm_add_meta_boxes() {
    add_meta_box(
        'uhm_spiel_stats_meta_box', // ID
        'Spieler-Statistiken (Tore und Assists)', // Title
        'uhm_spiel_stats_meta_box_callback', // Callback
        'uhm_spiel', // Screen (Post Type)
        'normal', // Context
        'default' // Priority
    );
}
add_action('add_meta_boxes', 'uhm_add_meta_boxes');

function uhm_spiel_stats_meta_box_callback($post) {
    // Nonce field to validate form request came from current site
    wp_nonce_field('uhm_save_spiel_stats_data', 'uhm_spiel_stats_meta_box_nonce');

    // Get currently saved data
    $saved_stats = get_post_meta($post->ID, '_uhm_spiel_stats', true);
    if (!is_array($saved_stats)) {
        $saved_stats = array();
    }

    // Get all players (Spieler)
    $spieler_args = array(
        'post_type' => 'uhm_spieler',
        'posts_per_page' => -1, // Get all
        'post_status' => 'publish'
    );
    $spieler_query = new WP_Query($spieler_args);

    echo '<table class="widefat fixed" cellspacing="0">';
    echo '<thead><tr>';
    echo '<th>Spieler</th>';
    echo '<th>Tore</th>';
    echo '<th>Assists</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    if ($spieler_query->have_posts()) {
        while ($spieler_query->have_posts()) {
            $spieler_query->the_post();
            $spieler_id = get_the_ID();
            $spieler_name = get_the_title();

            $tore = isset($saved_stats[$spieler_id]['tore']) ? $saved_stats[$spieler_id]['tore'] : 0;
            $assists = isset($saved_stats[$spieler_id]['assists']) ? $saved_stats[$spieler_id]['assists'] : 0;

            echo '<tr>';
            echo '<td>' . esc_html($spieler_name) . '</td>';
            echo '<td><input type="number" min="0" name="uhm_stats[' . $spieler_id . '][tore]" value="' . esc_attr($tore) . '" /></td>';
            echo '<td><input type="number" min="0" name="uhm_stats[' . $spieler_id . '][assists]" value="' . esc_attr($assists) . '" /></td>';
            echo '</tr>';
        }
        wp_reset_postdata();
    } else {
        echo '<tr><td colspan="3">Keine Spieler gefunden. Bitte zuerst Spieler erfassen.</td></tr>';
    }

    echo '</tbody>';
    echo '</table>';
}

function uhm_save_spiel_stats_data($post_id) {
    // Check if nonce is set
    if (!isset($_POST['uhm_spiel_stats_meta_box_nonce'])) {
        return;
    }
    // Verify nonce
    if (!wp_verify_nonce($_POST['uhm_spiel_stats_meta_box_nonce'], 'uhm_save_spiel_stats_data')) {
        return;
    }
    // If this is an autosave, our form has not been submitted
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    // Check user's permissions
    if (isset($_POST['post_type']) && 'uhm_spiel' == $_POST['post_type']) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    // Save the data
    if (isset($_POST['uhm_stats']) && is_array($_POST['uhm_stats'])) {
        $stats = array();
        foreach ($_POST['uhm_stats'] as $spieler_id => $data) {
            $tore = isset($data['tore']) ? intval($data['tore']) : 0;
            $assists = isset($data['assists']) ? intval($data['assists']) : 0;

            // Only save if tore or assists are > 0 to save DB space
            if ($tore > 0 || $assists > 0) {
                $stats[$spieler_id] = array(
                    'tore' => $tore,
                    'assists' => $assists
                );
            }
        }
        update_post_meta($post_id, '_uhm_spiel_stats', $stats);
    } else {
        delete_post_meta($post_id, '_uhm_spiel_stats');
    }
}
add_action('save_post', 'uhm_save_spiel_stats_data');

/**
 * Shortcodes for Frontend
 */

// Shortcode 1: Spielberichte anzeigen
function uhm_spiele_shortcode($atts) {
    // Extract attributes and set defaults
    $atts = shortcode_atts(array(
        'anzahl' => 10,
    ), $atts, 'uhm_spiele');

    $args = array(
        'post_type' => 'uhm_spiel',
        'posts_per_page' => intval($atts['anzahl']),
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    );

    $spiele_query = new WP_Query($args);

    ob_start(); // Start output buffering

    if ($spiele_query->have_posts()) {
        echo '<div class="uhm-spiele-liste">';
        while ($spiele_query->have_posts()) {
            $spiele_query->the_post();
            echo '<div class="uhm-spiel">';
            echo '<h3>' . get_the_title() . '</h3>';
            echo '<div class="uhm-spiel-datum">' . get_the_date() . '</div>';
            echo '<div class="uhm-spiel-bericht">' . apply_filters('the_content', get_the_content()) . '</div>';

            // Show stats for this game
            $stats = get_post_meta(get_the_ID(), '_uhm_spiel_stats', true);
            if (!empty($stats) && is_array($stats)) {
                echo '<h4>Spieler-Statistiken:</h4>';
                echo '<ul>';
                foreach ($stats as $spieler_id => $data) {
                    $spieler_name = get_the_title($spieler_id);
                    echo '<li>' . esc_html($spieler_name) . ': ' . intval($data['tore']) . ' Tore, ' . intval($data['assists']) . ' Assists</li>';
                }
                echo '</ul>';
            }

            echo '</div><hr>';
        }
        echo '</div>';
        wp_reset_postdata();
    } else {
        echo '<p>Keine Spiele gefunden.</p>';
    }

    return ob_get_clean(); // Return buffered output
}
add_shortcode('uhm_spiele', 'uhm_spiele_shortcode');

// Shortcode 2: Scorerliste anzeigen
function uhm_scorerliste_shortcode() {
    // 1. Get all games and their stats
    $spiele_args = array(
        'post_type' => 'uhm_spiel',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );
    $spiele_query = new WP_Query($spiele_args);

    $scorer_daten = array();

    if ($spiele_query->have_posts()) {
        while ($spiele_query->have_posts()) {
            $spiele_query->the_post();
            $stats = get_post_meta(get_the_ID(), '_uhm_spiel_stats', true);

            if (!empty($stats) && is_array($stats)) {
                foreach ($stats as $spieler_id => $data) {
                    if (!isset($scorer_daten[$spieler_id])) {
                        $scorer_daten[$spieler_id] = array(
                            'tore' => 0,
                            'assists' => 0,
                            'punkte' => 0,
                            'name' => get_the_title($spieler_id)
                        );
                    }
                    $tore = intval($data['tore']);
                    $assists = intval($data['assists']);

                    $scorer_daten[$spieler_id]['tore'] += $tore;
                    $scorer_daten[$spieler_id]['assists'] += $assists;
                    $scorer_daten[$spieler_id]['punkte'] += ($tore + $assists);
                }
            }
        }
        wp_reset_postdata();
    }

    // 2. Sort the array by punkte (descending), then tore (descending)
    usort($scorer_daten, function($a, $b) {
        if ($a['punkte'] == $b['punkte']) {
            if ($a['tore'] == $b['tore']) {
                return 0;
            }
            return ($a['tore'] > $b['tore']) ? -1 : 1;
        }
        return ($a['punkte'] > $b['punkte']) ? -1 : 1;
    });

    // 3. Render the table
    ob_start();

    echo '<table class="uhm-scorerliste">';
    echo '<thead><tr>';
    echo '<th>Rang</th>';
    echo '<th>Spieler</th>';
    echo '<th>Tore</th>';
    echo '<th>Assists</th>';
    echo '<th>Punkte</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    if (empty($scorer_daten)) {
        echo '<tr><td colspan="5">Noch keine Scorer-Daten vorhanden.</td></tr>';
    } else {
        $rang = 1;
        foreach ($scorer_daten as $spieler) {
            echo '<tr>';
            echo '<td>' . $rang . '</td>';
            echo '<td>' . esc_html($spieler['name']) . '</td>';
            echo '<td>' . $spieler['tore'] . '</td>';
            echo '<td>' . $spieler['assists'] . '</td>';
            echo '<td><strong>' . $spieler['punkte'] . '</strong></td>';
            echo '</tr>';
            $rang++;
        }
    }

    echo '</tbody>';
    echo '</table>';

    // Add some basic inline styles just in case
    echo '<style>
        .uhm-scorerliste { width: 100%; border-collapse: collapse; }
        .uhm-scorerliste th, .uhm-scorerliste td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .uhm-scorerliste th { background-color: #f2f2f2; }
    </style>';

    return ob_get_clean();
}
add_shortcode('uhm_scorerliste', 'uhm_scorerliste_shortcode');
