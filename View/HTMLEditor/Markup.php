<?php

namespace lightningsdk\core\View\HTMLEditor;

use DOMDocument;
use DOMElement;
use lightningsdk\core\Tools\Configuration;
use Exception;

class Markup {
    public static function render($content, &$vars = []) {
        // Replace special tags
        $renderers = Configuration::get('markup.renderers');
        foreach (self::getTags($content) as $match => $element) {
            $output = '';

            // If this is setting a var, add it to the var array
            if ($element->tagName === 'set') {
                $var = $element->getAttribute('var');
                $vars[$var] = $element->getAttribute('value');
            }

            // If a renderer exists, run it.
            if (isset($renderers[$element->nodeName])) {
                // First convert the attributes to an array.
                $options = static::getAttributeArray($element);
                try {
                    $output = call_user_func([$renderers[$element->nodeName], 'renderMarkup'], $options, $vars);
                } catch (Exception $e) {
                    $content = $e->getMessage();
                }
            }

            $content = str_replace(
                $match,
                $output,
                $content
            );
        }

        if (!empty($vars)) {
            // Conform variable names to uppercase.
            $conformed_vars = [];
            foreach ($vars as $key => $val) {
                $conformed_vars[strtoupper($key)] = $val;
            }

            // Replace variables.
            static::replaceVars('', $conformed_vars, $content);

            // Replace conditions.
            $conditions = [];
            $conditional_search = '/{IF ([a-z_0-9]+)}(.*){ENDIF \1}/imsU';
            preg_match_all($conditional_search, $content, $conditions);
            while (!empty($conditions[0])) {
                foreach ($conditions[1] as $key => $var) {
                    if (!empty($conformed_vars[$var]) || !empty($conformed_vars[$var])) {
                        $content = str_replace($conditions[0][$key], $conditions[2][$key], $content);
                    } else {
                        $content = str_replace($conditions[0][$key], '', $content);
                    }
                }
                preg_match_all($conditional_search, $content, $conditions);
            }
        }

        return $content;
    }

    public static function getTags($content, $tagName = '.*') {
        $matches = [];
        $elements = [];
        preg_match_all('|{{' . $tagName . '}}|sU', $content, $matches);
        foreach ($matches[0] as $match) {
            if (!empty($match)) {
                // Convert to HTML and parse it.
                $match_html = '<' . trim(preg_replace('/(\r?\n)/', ' ', $match), '{} ') . '/>';
                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadHTML($match_html);

                // For most HTML elements, a body wrapper will automatically be added. We have to remove it.
                $body = $dom->getElementsByTagName('body');

                if ($body->length > 0) {
                    $elements[$match] = $body->item(0)->childNodes->item(0);
                } else {
                    // If it's not in the body, we have to find it explicitly.
                    // This is the case for {{script ...}}
                    $nameMatch = [];
                    preg_match('/{{([a-z]+)/', $match, $nameMatch);
                    $element = $dom->getElementsByTagName($nameMatch[1]);
                    if ($element->length > 0) {
                        $elements[$match] = $element->item(0);
                    } else {
                        throw new Exception('Could not find reconstructed DOM elemnet.');
                    }
                }
            }
        }

        return $elements;
    }

    public static function removeAll($content) {
        preg_match_all('|{{.*}}|sU', $content, $matches);
        foreach ($matches[0] as $match) {
            if (!empty($match)) {
                $content = str_replace(
                    $match,
                    '',
                    $content
                );
            }
        }
        return $content;
    }

    protected static function getAttributeArray(DOMElement $element) {
        $options = [];
        foreach ($element->attributes as $attr => $value) {
            $options[$attr] = $element->getAttribute($attr);
        }
        return $options;
    }

    /**
     * A nestable function for replacing variables.
     *
     * @param string $prefix
     *   A prefix added to all variable names in the current array.
     * @param array $vars
     *   A list of variables to replace.
     * @param string $source
     *   The content to replace in.
     */
    protected static function replaceVars($prefix, $vars, &$source) {
        foreach($vars as $var => $value) {
            if (is_string($value)) {
                $find = $prefix . $var;
                // Replace simple variables as a string.
                $source = str_replace('{' . $find . '}', $value, $source);
                // Some curly brackets might be escaped if they are links.
                $source = str_replace('%7B' . $find . '%7D', $value, $source);
            } elseif (is_array($value)) {
                static::replaceVars($prefix . $var . '.', $value, $source);
            }
        }
    }
}
