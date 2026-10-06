<?php
/**
 * @var array $elementOptions
 */
?>
<div class="field">
    <div class="two columns alpha">
        <label for="language_labels_element_id"><?php echo __('Language element'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Element to read languages from. Separate several '
            . 'languages with separate texts, or with ; , or |.'); ?></p>
        <?php echo $this->formSelect(
            'language_labels_element_id',
            get_option('language_labels_element_id'),
            array(),
            $elementOptions
        ); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="language_labels_display"><?php echo __('Show as'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Codes and names are both recognised: "fre", "fr" and '
            . '"French" are the same language. Unrecognised values are shown as entered.'); ?></p>
        <?php echo $this->formSelect(
            'language_labels_display',
            get_option('language_labels_display'),
            array(),
            array(
                'code' => __('Short code (EN, FR)'),
                'name' => __('Language name (English, French)'),
                'raw'  => __('Value as entered'),
            )
        ); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="language_labels_position"><?php echo __('Position'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Corner of the thumbnail. Automatic positions use the '
            . 'side reading starts from: left on left-to-right pages, right on right-to-left pages.'); ?></p>
        <?php echo $this->formSelect(
            'language_labels_position',
            get_option('language_labels_position'),
            array(),
            array(
                'top-left'     => __('Top left'),
                'top-right'    => __('Top right'),
                'bottom-left'  => __('Bottom left'),
                'bottom-right' => __('Bottom right'),
                'auto-top'     => __('Top (automatic)'),
                'auto-bottom'  => __('Bottom (automatic)'),
            )
        ); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="language_labels_max"><?php echo __('Maximum labels'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Further languages collapse into a "+N" label. 0 for no limit.'); ?></p>
        <?php echo $this->formText(
            'language_labels_max',
            get_option('language_labels_max'),
            array('size' => 5)
        ); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="language_labels_auto_colour"><?php echo __('Colour by language'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Give each language its own colour, derived from the '
            . 'base colour. When off, the label colours below are used.'); ?></p>
        <?php echo $this->formCheckbox(
            'language_labels_auto_colour',
            null,
            array('checked' => (bool) get_option('language_labels_auto_colour')),
            array('1', '0')
        ); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="language_labels_base_colour"><?php echo __('Base colour'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('A hex colour, or a theme CSS variable with a hex '
            . 'fallback, e.g. var(--primary-color, #76D7C4). Other languages get the same shade '
            . 'in different hues.'); ?></p>
        <?php echo $this->formText(
            'language_labels_base_colour',
            get_option('language_labels_base_colour'),
            array('size' => 40)
        ); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="language_labels_base_language"><?php echo __('Base language'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Optional. Language shown in exactly the base colour, e.g. en or English.'); ?></p>
        <?php echo $this->formText(
            'language_labels_base_language',
            get_option('language_labels_base_language'),
            array('size' => 12)
        ); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="language_labels_colour_order"><?php echo __('Colour priority'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Comma-separated languages, most common first. Earlier '
            . 'languages get the most distinct colours.'); ?></p>
        <?php echo $this->formText(
            'language_labels_colour_order',
            get_option('language_labels_colour_order'),
            array('size' => 40)
        ); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="language_labels_background"><?php echo __('Label colours'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Background and text hex colours, used when colour by language is off.'); ?></p>
        <?php echo $this->formText(
            'language_labels_background',
            get_option('language_labels_background'),
            array('size' => 8)
        ); ?>
        <?php echo $this->formText(
            'language_labels_foreground',
            get_option('language_labels_foreground'),
            array('size' => 8)
        ); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="language_labels_custom_map"><?php echo __('Custom labels'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('One "value = label" per line; case-insensitive. An '
            . 'empty label hides the value, e.g. "zxx =".'); ?></p>
        <?php echo $this->formTextarea(
            'language_labels_custom_map',
            get_option('language_labels_custom_map'),
            array('rows' => 6, 'cols' => 40, 'placeholder' => "Old English = OE\nzxx =")
        ); ?>
    </div>
</div>

<div class="field">
    <div class="two columns alpha">
        <label for="language_labels_show_admin"><?php echo __('Show in admin'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Also label thumbnails on the admin Browse Items page.'); ?></p>
        <?php echo $this->formCheckbox(
            'language_labels_show_admin',
            null,
            array('checked' => (bool) get_option('language_labels_show_admin')),
            array('1', '0')
        ); ?>
    </div>
</div>
