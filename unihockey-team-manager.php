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

    // Register Taxonomy for Saison
    $saison_labels = array(
        'name'              => 'Saisons',
        'singular_name'     => 'Saison',
        'search_items'      => 'Saisons suchen',
        'all_items'         => 'Alle Saisons',
        'edit_item'         => 'Saison bearbeiten',
        'update_item'       => 'Saison aktualisieren',
        'add_new_item'      => 'Neue Saison hinzufügen',
        'new_item_name'     => 'Neuer Saison-Name',
        'menu_name'         => 'Saison',
    );

    $saison_args = array(
        'hierarchical'      => true, // Like categories
        'labels'            => $saison_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'saison'),
    );

    register_taxonomy('uhm_saison', array('uhm_spiel'), $saison_args);
}
add_action('init', 'uhm_register_cpts');

/**
 * Player Meta Boxes & Save Logic
 */
function uhm_add_spieler_meta_boxes() {
    add_meta_box('uhm_spieler_details', 'Spieler Details', 'uhm_spieler_details_callback', 'uhm_spieler', 'normal', 'high');
}
add_action('add_meta_boxes', 'uhm_add_spieler_meta_boxes');

function uhm_spieler_details_callback($post) {
    wp_nonce_field('uhm_save_spieler_data', 'uhm_spieler_nonce');

    $vorname = get_post_meta($post->ID, '_uhm_vorname', true);
    $nachname = get_post_meta($post->ID, '_uhm_nachname', true);
    $nummer = get_post_meta($post->ID, '_uhm_nummer', true);
    $position = get_post_meta($post->ID, '_uhm_position', true);
    if (!$position) $position = 'feldspieler';

    echo '<table class="form-table"><tbody>';
    echo '<tr><th><label for="uhm_vorname">Vorname</label></th><td><input type="text" id="uhm_vorname" name="uhm_vorname" value="'.esc_attr($vorname).'" class="regular-text"></td></tr>';
    echo '<tr><th><label for="uhm_nachname">Nachname</label></th><td><input type="text" id="uhm_nachname" name="uhm_nachname" value="'.esc_attr($nachname).'" class="regular-text"></td></tr>';
    echo '<tr><th><label for="uhm_nummer">Rückennummer</label></th><td><input type="text" id="uhm_nummer" name="uhm_nummer" value="'.esc_attr($nummer).'" class="small-text"></td></tr>';
    echo '<tr><th><label for="uhm_position">Position</label></th><td>';
    echo '<select id="uhm_position" name="uhm_position">';
    echo '<option value="feldspieler" '.selected($position, 'feldspieler', false).'>Feldspieler</option>';
    echo '<option value="torhueter" '.selected($position, 'torhueter', false).'>Torhüter</option>';
    echo '</select></td></tr>';
    echo '</tbody></table>';
}

function uhm_save_spieler_data($post_id) {
    if (!isset($_POST['uhm_spieler_nonce']) || !wp_verify_nonce($_POST['uhm_spieler_nonce'], 'uhm_save_spieler_data')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if ($_POST['post_type'] != 'uhm_spieler') return;

    if (isset($_POST['uhm_vorname'])) update_post_meta($post_id, '_uhm_vorname', sanitize_text_field($_POST['uhm_vorname']));
    if (isset($_POST['uhm_nachname'])) update_post_meta($post_id, '_uhm_nachname', sanitize_text_field($_POST['uhm_nachname']));
    if (isset($_POST['uhm_nummer'])) update_post_meta($post_id, '_uhm_nummer', sanitize_text_field($_POST['uhm_nummer']));
    if (isset($_POST['uhm_position'])) update_post_meta($post_id, '_uhm_position', sanitize_text_field($_POST['uhm_position']));

    // Auto-update Post Title
    $vorname = sanitize_text_field($_POST['uhm_vorname']);
    $nachname = sanitize_text_field($_POST['uhm_nachname']);
    $new_title = trim($vorname . ' ' . $nachname);
    if (!empty($new_title)) {
        remove_action('save_post', 'uhm_save_spieler_data'); // Prevent infinite loop
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $new_title
        ));
        add_action('save_post', 'uhm_save_spieler_data');
    }
}
add_action('save_post', 'uhm_save_spieler_data');

/**
 * Meta Box for Game Data & Stats
 */
function uhm_add_spiel_meta_boxes() {
    add_meta_box('uhm_spiel_details', 'Spieldetails & Roster', 'uhm_spiel_details_callback', 'uhm_spiel', 'normal', 'high');
    add_meta_box('uhm_spiel_timeline', 'Spielverlauf (Tore)', 'uhm_spiel_timeline_callback', 'uhm_spiel', 'normal', 'high');
}
add_action('add_meta_boxes', 'uhm_add_spiel_meta_boxes');

function uhm_spiel_details_callback($post) {
    wp_nonce_field('uhm_save_spiel_data', 'uhm_spiel_nonce');

    $gegner = get_post_meta($post->ID, '_uhm_gegner', true);
    $heim_gast = get_post_meta($post->ID, '_uhm_heim_gast', true);
    if (!$heim_gast) $heim_gast = 'heim'; // Default

    $roster = get_post_meta($post->ID, '_uhm_roster', true);
    if (!is_array($roster)) $roster = array();

    // Spieldetails
    echo '<h4>Spieldetails</h4>';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th><label for="uhm_gegner">Gegner</label></th><td><input type="text" id="uhm_gegner" name="uhm_gegner" value="'.esc_attr($gegner).'" class="regular-text"></td></tr>';
    echo '<tr><th><label>Spielort</label></th><td>';
    echo '<label><input type="radio" name="uhm_heim_gast" value="heim" '.checked($heim_gast, 'heim', false).'> Heimspiel (UHC Rappi Tigers)</label><br>';
    echo '<label><input type="radio" name="uhm_heim_gast" value="gast" '.checked($heim_gast, 'gast', false).'> Auswärtsspiel</label>';
    echo '</td></tr>';
    echo '</tbody></table>';

    // Roster
    echo '<h4>Kader (Roster) und Strafen</h4>';

    $spieler_args = array('post_type' => 'uhm_spieler', 'posts_per_page' => -1, 'post_status' => 'publish');
    $spieler_query = new WP_Query($spieler_args);

    echo '<table class="widefat fixed" cellspacing="0">';
    echo '<thead><tr><th>Gespielt?</th><th>Spieler</th><th>Position</th><th>2\' Strafen</th><th>5\' Strafen</th></tr></thead><tbody>';

    if ($spieler_query->have_posts()) {
        while ($spieler_query->have_posts()) {
            $spieler_query->the_post();
            $spieler_id = get_the_ID();
            $spieler_name = get_the_title();
            $nummer = get_post_meta($spieler_id, '_uhm_nummer', true);
            $position = get_post_meta($spieler_id, '_uhm_position', true);

            $display_name = (!empty($nummer) ? '#' . $nummer . ' ' : '') . $spieler_name;

            // Default to checked if post is new, otherwise use saved data
            $is_new = empty($post->post_date_gmt) || $post->post_date_gmt === '0000-00-00 00:00:00';
            $gespielt = $is_new ? true : isset($roster[$spieler_id]);
            $strafen_2 = isset($roster[$spieler_id]['strafen_2']) ? $roster[$spieler_id]['strafen_2'] : 0;
            $strafen_5 = isset($roster[$spieler_id]['strafen_5']) ? $roster[$spieler_id]['strafen_5'] : 0;

            echo '<tr>';
            echo '<td><input type="checkbox" name="uhm_roster['.$spieler_id.'][gespielt]" value="1" '.checked($gespielt, true, false).'></td>';
            echo '<td>' . esc_html($display_name) . '</td>';
            echo '<td>' . esc_html(ucfirst($position)) . '</td>';
            echo '<td><input type="number" min="0" name="uhm_roster['.$spieler_id.'][strafen_2]" value="'.esc_attr($strafen_2).'" class="small-text"></td>';
            echo '<td><input type="number" min="0" name="uhm_roster['.$spieler_id.'][strafen_5]" value="'.esc_attr($strafen_5).'" class="small-text"></td>';
            echo '</tr>';
        }
        wp_reset_postdata();
    } else {
        echo '<tr><td colspan="5">Keine Spieler gefunden.</td></tr>';
    }
    echo '</tbody></table>';
}

function uhm_spiel_timeline_callback($post) {
    $timeline = get_post_meta($post->ID, '_uhm_timeline', true);
    if (!is_array($timeline)) $timeline = array();

    // Get all players for dropdown
    $spieler_options = '<option value="">-- Niemand --</option>';
    $spieler_args = array('post_type' => 'uhm_spieler', 'posts_per_page' => -1, 'post_status' => 'publish');
    $spieler_query = new WP_Query($spieler_args);
    if ($spieler_query->have_posts()) {
        while ($spieler_query->have_posts()) {
            $spieler_query->the_post();
            $spieler_id = get_the_ID();
            $spieler_name = get_the_title();
            $nummer = get_post_meta($spieler_id, '_uhm_nummer', true);
            $display_name = (!empty($nummer) ? '#' . $nummer . ' ' : '') . $spieler_name;
            $spieler_options .= '<option value="'.esc_attr($spieler_id).'">'.esc_html($display_name).'</option>';
        }
        wp_reset_postdata();
    }

    echo '<div id="uhm-timeline-container">';
    echo '<table class="widefat fixed" id="uhm-timeline-table" cellspacing="0">';
    echo '<thead><tr><th>Tore UHC Rappi Tigers</th><th>Tore Gegner</th><th>Torschütze (Rappi)</th><th>Assist (Rappi)</th><th>Aktion</th></tr></thead>';
    echo '<tbody>';

    $index = 0;
    foreach ($timeline as $event) {
        $score_rappi = isset($event['score_rappi']) ? $event['score_rappi'] : 0;
        $score_gegner = isset($event['score_gegner']) ? $event['score_gegner'] : 0;
        $torschuetze = isset($event['torschuetze']) ? $event['torschuetze'] : '';
        $assist = isset($event['assist']) ? $event['assist'] : '';

        echo '<tr data-index="'.$index.'">';
        echo '<td><input type="number" min="0" name="uhm_timeline['.$index.'][score_rappi]" value="'.esc_attr($score_rappi).'" class="small-text"></td>';
        echo '<td><input type="number" min="0" name="uhm_timeline['.$index.'][score_gegner]" value="'.esc_attr($score_gegner).'" class="small-text"></td>';

        echo '<td><select name="uhm_timeline['.$index.'][torschuetze]">';
        echo str_replace('value="'.esc_attr($torschuetze).'"', 'value="'.esc_attr($torschuetze).'" selected', $spieler_options);
        echo '</select></td>';

        echo '<td><select name="uhm_timeline['.$index.'][assist]">';
        echo str_replace('value="'.esc_attr($assist).'"', 'value="'.esc_attr($assist).'" selected', $spieler_options);
        echo '</select></td>';

        echo '<td><button type="button" class="button remove-timeline-row">Entfernen</button></td>';
        echo '</tr>';
        $index++;
    }

    echo '</tbody></table>';
    echo '<p><button type="button" class="button button-primary" id="add-timeline-row">Tor hinzufügen</button></p>';
    echo '</div>';

    // Inline JS to handle dynamic rows
    ?>
    <script>
    jQuery(document).ready(function($){
        var index = <?php echo $index; ?>;
        var playerOptions = `<?php echo $spieler_options; ?>`;

        $('#add-timeline-row').click(function(){
            var row = '<tr data-index="'+index+'">' +
                '<td><input type="number" min="0" name="uhm_timeline['+index+'][score_rappi]" value="0" class="small-text"></td>' +
                '<td><input type="number" min="0" name="uhm_timeline['+index+'][score_gegner]" value="0" class="small-text"></td>' +
                '<td><select name="uhm_timeline['+index+'][torschuetze]">'+playerOptions+'</select></td>' +
                '<td><select name="uhm_timeline['+index+'][assist]">'+playerOptions+'</select></td>' +
                '<td><button type="button" class="button remove-timeline-row">Entfernen</button></td>' +
                '</tr>';
            $('#uhm-timeline-table tbody').append(row);
            index++;
        });

        $(document).on('click', '.remove-timeline-row', function(){
            $(this).closest('tr').remove();
        });
    });
    </script>
    <?php
}

function uhm_save_spiel_data($post_id) {
    if (!isset($_POST['uhm_spiel_nonce']) || !wp_verify_nonce($_POST['uhm_spiel_nonce'], 'uhm_save_spiel_data')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if ($_POST['post_type'] != 'uhm_spiel') return;

    // Save Details
    if (isset($_POST['uhm_gegner'])) update_post_meta($post_id, '_uhm_gegner', sanitize_text_field($_POST['uhm_gegner']));
    if (isset($_POST['uhm_heim_gast'])) update_post_meta($post_id, '_uhm_heim_gast', sanitize_text_field($_POST['uhm_heim_gast']));

    // Save Roster
    $roster = array();
    if (isset($_POST['uhm_roster']) && is_array($_POST['uhm_roster'])) {
        foreach ($_POST['uhm_roster'] as $spieler_id => $data) {
            if (isset($data['gespielt']) && $data['gespielt'] == '1') {
                $roster[$spieler_id] = array(
                    'strafen_2' => isset($data['strafen_2']) ? intval($data['strafen_2']) : 0,
                    'strafen_5' => isset($data['strafen_5']) ? intval($data['strafen_5']) : 0,
                );
            }
        }
    }
    update_post_meta($post_id, '_uhm_roster', $roster);

    // Save Timeline
    $timeline = array();
    if (isset($_POST['uhm_timeline']) && is_array($_POST['uhm_timeline'])) {
        foreach ($_POST['uhm_timeline'] as $event) {
            // Only add valid timeline events
            $timeline[] = array(
                'score_rappi' => isset($event['score_rappi']) ? intval($event['score_rappi']) : 0,
                'score_gegner' => isset($event['score_gegner']) ? intval($event['score_gegner']) : 0,
                'torschuetze' => isset($event['torschuetze']) ? sanitize_text_field($event['torschuetze']) : '',
                'assist' => isset($event['assist']) ? sanitize_text_field($event['assist']) : '',
            );
        }
    }
    update_post_meta($post_id, '_uhm_timeline', $timeline);
}
add_action('save_post', 'uhm_save_spiel_data');

/**
 * Shortcodes for Frontend
 */

// Helper to get formatted player name
function uhm_get_player_name_formatted($spieler_id) {
    if (!$spieler_id) return '';
    $vorname = get_post_meta($spieler_id, '_uhm_vorname', true);
    $nachname = get_post_meta($spieler_id, '_uhm_nachname', true);
    if (!empty($vorname)) {
        return esc_html($vorname . ' (' . substr($nachname, 0, 4) . '.)'); // E.g. Jan (Rafi)
    }
    return esc_html(get_the_title($spieler_id));
}

// Shortcode 1: Spielberichte anzeigen
function uhm_spiele_shortcode($atts) {
    $atts = shortcode_atts(array(
        'anzahl' => 10,
        'saison' => '',
    ), $atts, 'uhm_spiele');

    $args = array(
        'post_type' => 'uhm_spiel',
        'posts_per_page' => intval($atts['anzahl']),
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    );

    if (!empty($atts['saison'])) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'uhm_saison',
                'field'    => 'slug',
                'terms'    => $atts['saison'],
            ),
        );
    }

    $spiele_query = new WP_Query($args);
    ob_start();

    if ($spiele_query->have_posts()) {
        echo '<div class="uhm-spiele-liste">';
        while ($spiele_query->have_posts()) {
            $spiele_query->the_post();
            $post_id = get_the_ID();

            $gegner = get_post_meta($post_id, '_uhm_gegner', true);
            $heim_gast = get_post_meta($post_id, '_uhm_heim_gast', true);
            $timeline = get_post_meta($post_id, '_uhm_timeline', true);

            $team_rappi = 'UHC Rappi Tigers';
            $team_gegner = !empty($gegner) ? $gegner : 'Gegner';

            $is_heim = ($heim_gast !== 'gast');
            $team_left = $is_heim ? $team_rappi : $team_gegner;
            $team_right = $is_heim ? $team_gegner : $team_rappi;

            // Get final score
            $final_rappi = 0;
            $final_gegner = 0;
            if (!empty($timeline) && is_array($timeline)) {
                $last_event = end($timeline);
                $final_rappi = $last_event['score_rappi'];
                $final_gegner = $last_event['score_gegner'];
            }
            $score_left = $is_heim ? $final_rappi : $final_gegner;
            $score_right = $is_heim ? $final_gegner : $final_rappi;

            echo '<div class="uhm-spiel">';
            echo '<h3>' . get_the_title() . '</h3>';
            echo '<div class="uhm-spiel-datum">' . get_the_date() . '</div>';
            echo '<div class="uhm-spiel-bericht">' . apply_filters('the_content', get_the_content()) . '</div>';

            // Render Timeline
            if (!empty($timeline) && is_array($timeline)) {
                echo '<table class="uhm-timeline-table" style="width:100%; border-collapse:collapse; margin-top:20px;">';
                echo '<thead><tr style="border-bottom: 2px solid #ddd;">';
                echo '<th style="text-align:right; width:45%; padding:10px;">'.esc_html($team_left).'</th>';
                echo '<th style="text-align:center; width:10%; padding:10px;">' . $score_left . ' : ' . $score_right . '</th>';
                echo '<th style="text-align:left; width:45%; padding:10px;">'.esc_html($team_right).'</th>';
                echo '</tr></thead>';
                echo '<tbody>';

                $row_count = 0;
                foreach ($timeline as $event) {
                    $bg_color = ($row_count % 2 == 0) ? '#f9ebec' : 'transparent';

                    $score_rappi_cur = $event['score_rappi'];
                    $score_gegner_cur = $event['score_gegner'];
                    $score_left_cur = $is_heim ? $score_rappi_cur : $score_gegner_cur;
                    $score_right_cur = $is_heim ? $score_gegner_cur : $score_rappi_cur;

                    $is_rappi_goal = false;
                    // Try to guess if it's a Rappi goal based on if torschuetze is set
                    if (!empty($event['torschuetze'])) {
                        $is_rappi_goal = true;
                    }

                    $rappi_text = '';
                    if ($is_rappi_goal) {
                        $torschuetze_name = uhm_get_player_name_formatted($event['torschuetze']);
                        $assist_name = uhm_get_player_name_formatted($event['assist']);
                        $rappi_text = $torschuetze_name;
                        if (!empty($assist_name)) {
                            $rappi_text .= ' (' . $assist_name . ')';
                        }
                    }

                    $left_cell = '';
                    $right_cell = '';

                    if ($is_rappi_goal) {
                        if ($is_heim) {
                            $left_cell = $rappi_text;
                        } else {
                            $right_cell = $rappi_text;
                        }
                    } else {
                        // Gegner Goal
                        if ($is_heim) {
                            $right_cell = $team_gegner;
                        } else {
                            $left_cell = $team_gegner;
                        }
                    }

                    echo '<tr style="background-color:'.$bg_color.';">';
                    echo '<td style="text-align:left; padding:5px;">'.$left_cell.'</td>';
                    echo '<td style="text-align:center; padding:5px;">'.$score_left_cur.' &nbsp;&nbsp; '.$score_right_cur.'</td>';
                    echo '<td style="text-align:right; padding:5px;">'.$right_cell.'</td>';
                    echo '</tr>';

                    $row_count++;
                }
                echo '</tbody></table>';
            }

            echo '</div><hr>';
        }
        echo '</div>';
        wp_reset_postdata();
    } else {
        echo '<p>Keine Spiele gefunden.</p>';
    }
    return ob_get_clean();
}
add_shortcode('uhm_spiele', 'uhm_spiele_shortcode');

// Shortcode 2: Scorerliste anzeigen
function uhm_scorerliste_shortcode($atts) {
    $atts = shortcode_atts(array(
        'saison' => '',
    ), $atts, 'uhm_scorerliste');

    $args = array(
        'post_type' => 'uhm_spiel',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    if (!empty($atts['saison'])) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'uhm_saison',
                'field'    => 'slug',
                'terms'    => $atts['saison'],
            ),
        );
    }

    $spiele_query = new WP_Query($args);

    $scorer_daten = array();

    // Init players
    $spieler_args = array('post_type' => 'uhm_spieler', 'posts_per_page' => -1, 'post_status' => 'publish');
    $spieler_query = new WP_Query($spieler_args);
    if ($spieler_query->have_posts()) {
        while ($spieler_query->have_posts()) {
            $spieler_query->the_post();
            $id = get_the_ID();
            $scorer_daten[$id] = array(
                'id' => $id,
                'nummer' => get_post_meta($id, '_uhm_nummer', true),
                'vorname' => get_post_meta($id, '_uhm_vorname', true),
                'nachname' => get_post_meta($id, '_uhm_nachname', true),
                'name' => get_the_title($id),
                'position' => get_post_meta($id, '_uhm_position', true),
                'gp' => 0,
                'g' => 0,
                'a' => 0,
                'p' => 0,
                'strafen_2' => 0,
                'strafen_5' => 0
            );
        }
        wp_reset_postdata();
    }

    if ($spiele_query->have_posts()) {
        while ($spiele_query->have_posts()) {
            $spiele_query->the_post();
            $post_id = get_the_ID();

            // Add GP and Penalties from Roster
            $roster = get_post_meta($post_id, '_uhm_roster', true);
            if (is_array($roster)) {
                foreach ($roster as $p_id => $data) {
                    if (isset($scorer_daten[$p_id])) {
                        $scorer_daten[$p_id]['gp'] += 1;
                        $scorer_daten[$p_id]['strafen_2'] += isset($data['strafen_2']) ? intval($data['strafen_2']) : 0;
                        $scorer_daten[$p_id]['strafen_5'] += isset($data['strafen_5']) ? intval($data['strafen_5']) : 0;
                    }
                }
            }

            // Add G and A from Timeline
            $timeline = get_post_meta($post_id, '_uhm_timeline', true);
            if (is_array($timeline)) {
                foreach ($timeline as $event) {
                    if (!empty($event['torschuetze']) && isset($scorer_daten[$event['torschuetze']])) {
                        $scorer_daten[$event['torschuetze']]['g'] += 1;
                        $scorer_daten[$event['torschuetze']]['p'] += 1;
                    }
                    if (!empty($event['assist']) && isset($scorer_daten[$event['assist']])) {
                        $scorer_daten[$event['assist']]['a'] += 1;
                        $scorer_daten[$event['assist']]['p'] += 1;
                    }
                }
            }
        }
        wp_reset_postdata();
    }

    // Filter out players with 0 GP if you want (optional), but let's keep them if they are in the team, or filter 0 GP
    $feldspieler = array();
    $torhueter = array();

    foreach ($scorer_daten as $p) {
        if ($p['gp'] > 0 || $p['p'] > 0) { // Only show if they played or scored
            if ($p['position'] == 'torhueter') {
                $torhueter[] = $p;
            } else {
                $feldspieler[] = $p;
            }
        }
    }

    // Sort function: P (desc), GP (asc), G (desc), Name (asc)
    $sort_func = function($a, $b) {
        if ($a['p'] != $b['p']) return ($a['p'] > $b['p']) ? -1 : 1;
        if ($a['gp'] != $b['gp']) return ($a['gp'] < $b['gp']) ? -1 : 1; // Less games = better
        if ($a['g'] != $b['g']) return ($a['g'] > $b['g']) ? -1 : 1;
        return strcmp($a['name'], $b['name']);
    };

    usort($feldspieler, $sort_func);
    usort($torhueter, $sort_func);

    ob_start();

    $saison_text = !empty($atts['saison']) ? ' Saison ' . esc_html(ucfirst($atts['saison'])) : '';
    echo '<div class="uhm-scorerliste-container" style="font-family:sans-serif;">';
    echo '<h2>Scorerliste UHC Rappi Tigers' . $saison_text . '</h2>';

    // Helper function to render table
    function uhm_render_scorer_table($title, $players) {
        echo '<table style="width:100%; border-collapse:collapse; margin-bottom: 20px; text-align:left;">';
        echo '<thead><tr style="border-bottom: 1px solid #ddd;">';
        echo '<th style="padding:10px;">#</th>';
        echo '<th style="padding:10px;">' . $title . '</th>';
        echo '<th style="padding:10px; text-align:center;">GP</th>';
        echo '<th style="padding:10px; text-align:center;">G</th>';
        echo '<th style="padding:10px; text-align:center;">A</th>';
        echo '<th style="padding:10px; text-align:center;">P</th>';
        echo '<th style="padding:10px; text-align:center;">2\'</th>';
        echo '<th style="padding:10px; text-align:center;">5\'</th>';
        echo '</tr></thead><tbody>';

        if (empty($players)) {
            echo '<tr><td colspan="8" style="padding:10px;">Keine Spieler gefunden.</td></tr>';
        } else {
            foreach ($players as $p) {
                $num = !empty($p['nummer']) ? $p['nummer'] : '??';
                $name = $p['vorname'] . ' ' . $p['nachname'];
                if (trim($name) == '') $name = $p['name'];

                echo '<tr style="border-bottom: 1px solid #eee;">';
                echo '<td style="padding:10px;">' . esc_html($num) . '</td>';
                echo '<td style="padding:10px;">' . esc_html($name) . '</td>';
                echo '<td style="padding:10px; text-align:center;">' . $p['gp'] . '</td>';
                echo '<td style="padding:10px; text-align:center;">' . $p['g'] . '</td>';
                echo '<td style="padding:10px; text-align:center;">' . $p['a'] . '</td>';
                echo '<td style="padding:10px; text-align:center;"><strong>' . $p['p'] . '</strong></td>';
                echo '<td style="padding:10px; text-align:center;">' . $p['strafen_2'] . '</td>';
                echo '<td style="padding:10px; text-align:center;">' . $p['strafen_5'] . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
    }

    uhm_render_scorer_table('Feldspieler', $feldspieler);

    echo '<h3>Torhüter</h3>';
    uhm_render_scorer_table('Torhüter', $torhueter);

    echo '<p style="font-size:0.9em; color:#666;">Legende: # (Trikotnummer), GP (Spiele), G (Tore), A (Pässe), P (Punkte), 2\' (2-Minuten Strafen), 5\' (5-Minuten Strafen)</p>';
    echo '</div>';

    return ob_get_clean();
}
add_shortcode('uhm_scorerliste', 'uhm_scorerliste_shortcode');
