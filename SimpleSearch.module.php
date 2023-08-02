<?php namespace ProcessWire; 

class SimpleSearch extends WireData implements Module {

    public static function getModuleInfo() {

        return array(
            'title' => 'Simple Search',
            'version' => '1.0.0',
            'summary' => 'A simple search module for ProcessWire.',
            'autoload' => true,
            'singular' => true,
            'icon' => 'search',
            'author' => 'FRE:D',
        );

    }


    public function init() {

        // Define the pages to search
        $this->indexedCategories = ["", "project", "article"];
        $this->allResultsLabel = __("Alle Inhalte");
        
        $this->q = '';
        $this->cat = 0;
        
        $this->results = new WireArray;
        $this->totals = new WireArray;
        $this->labels = new WireArray;

        $this->inputSanitized = $this->sanitizeInput();

        $this->limit = 20;
        $this->sublimit = 10;
        // $this->start = $this->updateStart();
        $this->start = $this->limit * ($this->input->pageNum() - 1);

        // Explicitly load the MarkupPagerNav module
        $this->pager = $this->modules->get('MarkupPagerNav');
        
        $this->handleSearch();
    
    }


    protected function updateStart() {

        $start = $this->limit * ($this->input->pageNum() - 1);
        return $start;
    
    }


    protected function sanitizeInput() {

        // Sanitize the search term input
        $sanitizer = $this->wire('sanitizer');
        $input = $this->wire('input');
    
        // Sanitize and store the search term 'q'
        if (isset($input->get->q)) {
            $this->q = $sanitizer->text($input->get->q);
        } else {
            $this->q = null; // or $this->q = ''; if you prefer an empty string
        }
    
        // Sanitize and store the category 'cat'
        if (isset($input->get->cat)) {
            $this->cat = $sanitizer->int($input->get->cat);
        } else {
            $this->cat = 0; // or any other default value you want
        }

    }
    

    protected function handleSearch() {

        // Check if the search form was submitted (i.e., input variable exists)
        if ($this->q) {

            $indexedCategories = $this->indexedCategories;

            $allTotals = 0;

            foreach ($indexedCategories as $cat => $category) {

                if ($cat == 0) continue;

                // Pass the sanitized input to createSelector() to get the selector string.
                $selector = $this->createSelector($this->q, $category);

                $matches = $this->pages("$selector, start=0, limit=99999");

                // Calculate the total matches and the start index for the current page
                $total = count($matches);

                $this->results->set($cat, $matches);
                $this->totals->set($cat, $total);
                $this->labels->set($cat, $this->templates->get("$category")->label);                

                $allTotals += $total;

            }

            $this->results->prepend('');
            $this->totals->prepend($allTotals);
            $this->labels->prepend($this->allResultsLabel);

            // Update the total count for all results
            $this->totals->set(0, $allTotals);

        } 
        
    }


    protected function createSelector($q, $category) {

        $selector = "template=" . $category;

        $fields = $this->getUniqueFieldsFromTemplate($category);
        $selector .= ", " . implode('|', $fields) . "~=$q";

        return $selector;

    }


    // Helper method to extract unique fields from an array of templates
    protected function getUniqueFieldsFromTemplate($category) {

        $fields = [];

        // Replace this loop with the logic to extract the fields from the templates
        // For example, if each template has a property "$fields" containing an array of field names:
        $template = $this->templates->get("$category");
        foreach ($template->fields as $field) {

            if (strpos($field->type, "Text") == false && strpos($field->type, "Title") == false) continue;
            $fields[] = $field->name; // Store the name of the field in the $fields array
        }

        return array_unique($fields);

    }
    

    public function renderCriteriaMarkup() {

        if (!$this->q) return;

        $html = '';

        if (isset($this->cat) && $this->cat > 0 ) {
            $html .= "in: ";
            $html .= $this->indexedCategories[$this->cat];    
        } else {
            $html .= "in: $this->allResultsLabel";
        }

        $html .= ", ";
        $html .= "Suchbegriff: ";
        $html .= $this->q;
        
        if (isset($this->sort) && $this->sort != '') {
            $html .= ", ";
            $html .= "Sortierung: ";
            $html .= $this->sort;
        }

        return $html;

    }


    public function renderOverviewMarkup() {

        if (!$this->q) return;

        $html = '';

        // overview :D

        $cat = $this->cat;
        $allTotals = $this->totals->eq(0);

        if ($allTotals > 0) {
            if ($cat == 0 || !$cat) {
                $html .= '<strong>' . $this->allResultsLabel . ' (' . $allTotals . '), </strong>';
            } else {
                $html .= '<a class="colorlinks" href="./?q=' . $this->q . '">' . $this->allResultsLabel . ' (' . $allTotals . '), </a>';
            }
        }

        // still overview :D

        foreach ($this->indexedCategories as $key => $content) {
            if ($key == 0) continue;
            $total = $this->totals->eq($key);
            if ($total < 1) {
                $html .= '<strong class="grey">' . $this->labels->eq($key) . ' (' . $total . '), </strong>';
            } else {
                if ($cat == $key) {
                    $html .= '<strong>' . $this->labels->eq($key) . ' (' . $total . '), </strong>';
                } else {
                    $html .= '<a class="colorlinks" href="./?q=' . $this->q . '&cat=' . $key . '">' . $this->labels->eq($key) . ' (' . $total . '), </a>';
                }
            }
        }

        $html .= '<br/><h4><hr>';

        return $html;

    }


    public function renderResultsMarkup() {

        if (!$this->q) return;
        
        $allTotals = $this->totals->eq(0);
        $cat = $this->cat;

        $html = '';

        // results :D

        if ($cat == 0) {
            foreach ($this->results as $key => $matches) {
                if ($key == 0) continue; 
                $total = $this->totals->eq($key); 
                if ($total < 1) continue;

                $html .= '<h3><a class="colorlinks" href="./?q=' . $this->q . '&cat=' . $key . '">' . $this->labels->eq($key) . ' (' . $total . ')</a></h3>';
                $html .= '<ul class="nostyle">';

                $limit = $this->sublimit;

                $matches->filter("limit=$limit");

                foreach ($matches as $i => $match) {
                    $source = $this->indexedCategories[$cat];
                    // $html .= layout('search_' . $source, $item);
                    $html .= '<a href="'.$match->url.'" target="_blank">'.$match->title.'</a>';
                    $html .= '<hr>';
                }

                $html .= '</ul>';

                if ($total > $this->sublimit && $cat == 0) {
                    $html .= '<h3><a class="colorlinks" href="./?q=' . $this->q . '&cat=' . $key . '">mehr…</a></h3>';
                }

                $html .= '<hr>';
            }
        } else {
            $total = $this->totals->eq($cat); 
            $html .= '<h3><strong>' . $this->templates->get($this->indexedCategories[$cat])->label . ' (' . $total . ')</strong></h3>';
            $html .= '<ul class="nostyle">';

            $matches = $this->results->eq($cat);
            $start = $this->updateStart();
            $limit = $this->limit;
            $pagMatches = $matches->find("start=$start, limit=$limit");

            foreach ($pagMatches as $i => $match) {
                // $source = $this->templates->get($this->indexedCategories[$cat])->label;
                // $html .= layout('search_' . $source, $item);
                $ii = $i + 1;
                $html .= $ii . ' - ';
                $html .= '<a href="'.$match->url.'" target="_blank">'.$match->title.'</a>';
                $html .= '<hr>';
            }

            $html .= '</ul>';
            $html .= '<hr>';
        }

        return $html;

    }


    public function renderPaginationString() {

        if (!$this->q) return;

        // pagination string :D

        $cat = $this->cat;

        $html = '';

        if ($cat > 0) {

            $matches = $this->results->eq($cat);
            $start = $this->updateStart();
            $limit = $this->limit;
            $pagMatches = $matches->find("start=$start, limit=$limit");

            if ($pagMatches->count) {
                $html .= '<span class="grey">' . $pagMatches->getPaginationString(array(
                    'label' => 'Einträge',
                    'zeroLabel' => '0 Einträge', // 3.0.127+ only
                    'usePageNum' => false,
                    'count' => $pagMatches->count(),
                    'start' => $pagMatches->getStart(),
                    'limit' => $pagMatches->getLimit(),
                    'total' => $this->totals->eq($cat)
                    // 'count' => $pagMatches->count(),
                    // 'start' => $this->updateStart(),
                    // 'limit' => $this->limit,
                    // 'total' => $this->totals->eq($cat)
                )) . '</span>';
            }
        }

        return $html;        
        
    }


    public function renderFilters() {

        $html = '<form action="./" method="get">';
        $qValue = isset($this->q) ? htmlentities($this->q) : '';
        $html .= '<label for="q">Search:</label>';
        $html .= '<input type="text" id="q" name="q" value="' . $qValue . '">';
        $html .= '<input type="submit" value="Search">';
        $html .= '</form>';
    
        return $html;

    }
    

    public function renderPaginationMarkup() {
    
        $options = array(
            'listClass' => 'pagination noselect uk-flex uk-flex-wrap uk-flex-center',
            'linkMarkup' => "<a href='{url}?q={$this->q}&cat={$this->cat}'>{out}</a>",
            'currentItemClass' => 'current',
            'separatorItemLabel' => '…',
            'separatorItemClass' => 'uk-disabled',
            'previousItemClass' => 'nextprev',
            'nextItemClass' => 'nextprev',
            'currentLinkMarkup' => '<span class="current">{out}</span>',
            'nextItemLabel' => '<span uk-icon="icon: arrow-right; ratio: 1.8;"></span>',
            'previousItemLabel' => '<span uk-icon="icon: arrow-left; ratio: 1.8;"></span>',
            'numPageLinks' => '4',
            'lastItemClass' => ''
        );
        
        $html = '';
    
        // Get the total number of results for the current category
        $totalResults = $this->totals->eq($this->cat);
    
        $pager = $this->wire('modules')->get('MarkupPagerNav');

        // Check if we need to render pagination links
        if ($this->cat > 0 && $this->q != '') {
            // Update the 'total' option in the $options array with the total number of results
            $options['total'] = $totalResults;
    
            // Create a new WireArray containing only the paginated matches
            $matches = $this->results->eq($this->cat);
            $start = $this->updateStart();
            $limit = $this->limit;
            $matches->setStart($start);
            $matches->setLimit($limit);
        
            // Render the pagination links
            $html .= '<section>';
            $html .= '<div class="uk-flex uk-flex-center">' . $pager->render($matches, $options) . '</div>';
            $html .= '</section>';
        } 
    
        // Return the stored HTML
        return $html;
    }
        
}
