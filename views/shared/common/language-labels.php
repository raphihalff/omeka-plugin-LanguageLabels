<?php
/**
 * Label list for one item. Override by copying to your theme's common/ folder.
 * Keep the language-labels and language-labels--inline classes and dir="auto".
 *
 * @var Item $item
 * @var array $labels See LanguageLabelsPlugin::getLabels().
 * @var string $position
 * @var bool $autoColour
 */
$classes = 'language-labels language-labels--inline language-labels--' . $position;
if ($autoColour) {
    $classes .= ' language-labels--auto';
}
?>
<ul class="<?php echo $classes; ?>"<?php if (!$labels) echo ' hidden'; ?>
    aria-label="<?php echo html_escape(__('Languages')); ?>">
    <?php foreach ($labels as $label): ?>
    <li class="language-label<?php if (!empty($label['more'])) echo ' language-label--more'; ?>" dir="auto"
        title="<?php echo html_escape($label['title']); ?>"
        <?php if ($label['offset'] !== null): ?>style="--language-label-offset: <?php echo (int) $label['offset']; ?>"<?php endif; ?>>
        <?php if ($label['title'] !== $label['text']): ?>
        <span aria-hidden="true"><?php echo html_escape($label['text']); ?></span>
        <span class="language-label__sr"><?php echo html_escape($label['title']); ?></span>
        <?php else: ?>
        <?php echo html_escape($label['text']); ?>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
