<div id='content' class="row">
    <div id='inner-content' class="content_panel padding">
        <?php

        use Lightning\Pages\Page;
        use Lightning\Tools\Configuration;
        use Lightning\View\HTMLEditor\HTMLEditor;
        use Lightning\View\SocialLinks;

        if (!empty($editable)): ?>
            <div class="page_edit_links">
                <a href='/page?action=new'>New Page</a> | <a href='#' onclick='lightning.page.edit();return false;'>Edit This Page</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($editable)): ?>
            <div class='page_edit' <?php if (empty($action) || $action != 'new'): ?>style="display:none;"<?php endif; ?>>
                <input type="button" name="submit" class='button' onclick="lightning.page.save()" value="Save" /><br />
                <?php if (!empty($action) && $action == 'new'): ?>
                    <input type="hidden" name="action" id='page_action' value="submit_new" class="button" />
                <?php else: ?>
                    <input type="hidden" name="action" id='page_action' value="update_page" class="button" />
                <?php endif; ?>
                <input type="hidden" name="page_id" id='page_id' value="<?= !empty($full_page['page_id']) ? $full_page['page_id'] : 0 ?>" />
                <?= \Lightning\Tools\Form::renderTokenInput(); ?>
                <table border='0' width="100%">
                    <tr><td>Title:</td><td><input type="text" name="title" id='page_title' value="<?=!empty($full_page['new_title']) ? $full_page['new_title'] : $full_page['title']?>" /></td></tr>
                    <tr><td>URL:</td><td><input type="text" name="url" id='page_url' value="<?=$full_page['url']?>" /></td></tr>
                    <tr><td>Menu Context:</td><td><input type="text" name="menu_context" id='page_menu_context' value="<?=$full_page['menu_context']?>" /></td></tr>
                    <tr><td>Description:</td><td><input type="text" name="description" id='page_description' value="<?=$full_page['description']?>" /></td></tr>
                    <tr><td>Keywords:</td><td><input type="text" name="keywords" id='page_keywords' value="<?=$full_page['keywords']?>" /></td></tr>
                    <tr><td>Include in site map:</td><td><input type="checkbox" name="sitemap" id='page_sitemap' value="1" <?php if ( $full_page['site_map'] == 1):?>checked="true"<?php endif; ?> /></td></tr>
                    <tr><td>Hide Side Bar:</td><td><?= Page::layoutOptions($full_page['layout']); ?></td></tr>
                </table>
            </div>
            <?= HTMLEditor::div('page_display',
                array('spellcheck' => true, 'content' => $full_page['body_rendered'], 'browser' => true, 'startup' => false)
            ); ?>
        <?php else: ?>
            <?= $full_page['body_rendered']; ?>
        <?php endif; ?>
        <?php if (!empty($editable)):?>
            <input type="button" name="submit" class='button page_edit' onclick="lightning.page.save();" value="Save" <?php if (empty($action) || $action != 'new'):?>style="display:none;"<?php endif; ?> /><br />
        <?php endif; ?>

        <?php if (!empty($full_page['error'])): ?>
            <div class="social-share"><?= SocialLinks::render(Configuration::get('web_root') . '/' . (!empty($full_page['url']) ? $full_page['url'] . '.html' : '')); ?></div>
        <?php endif; ?>
    </div>
</div>
