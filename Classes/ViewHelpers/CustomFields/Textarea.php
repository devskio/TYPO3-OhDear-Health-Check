<?php

namespace Devskio\Typo3OhDearHealthCheck\ViewHelpers\CustomFields;

class Textarea
{
    public function render($parameters, $parentObject) {

        // Generate the HTML for the textarea
        $html = '<textarea name="' . htmlspecialchars($parameters['fieldName']) . '" style="width: 100%; height: 150px">' . htmlspecialchars($parameters['fieldValue']) . '</textarea>';

        return $html;
    }
}
