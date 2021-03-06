<?php

namespace Foolz\Foolfuuka\Theme\Foolfuuka\Partial;

class ToolsSearch extends \Foolz\Foolfuuka\View\View
{

    public function toString()
    {
        $radix = $this->getBuilderParamManager()->getParam('radix');
        $search = $this->getBuilderParamManager()->getParam('search', []);

        if (is_null($radix) && $this->getPreferences()->get('foolfuuka.sphinx.global')) {
            // search can work also without a radix selected
            $search_radix = '_';
        } elseif (!is_null($radix)) {
            $search_radix = $radix->shortname;
        }

        if (isset($search_radix)) : ?>

        <ul class="nav pull-right">
        <?= \Form::open([
            'class' => 'navbar-search',
            'method' => 'POST',
            'action' => $this->getUri()->create($search_radix.'/search')
        ]);
        ?>

        <li>
        <?= \Form::input([
            'name' => 'text',
            'value' => (isset($search["text"])) ? rawurldecode($search["text"]) : '',
            'class' => 'search-query',
            'placeholder' => ($search_radix  !== '_') ? _i('Search or insert post number') : _i('Search through all the boards')
        ]); ?>
        </li>
        <?= \Form::close() ?>
        </ul>
        <?php endif;
    }
}
