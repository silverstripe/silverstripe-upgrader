<?php

namespace SilverStripe\Upgrader\CodeCollection;

/**
 * Tool for searching a regex in a Code Collection.
 */
class CodeGrep
{

    /**
     * Regular expression to search the files for.
     * @var string
     */
    private $pattern;

    /**
     * Code base to search
     * @var CollectionInterface
     */
    private $collection;


    /**
     * @param string $pattern Regex pattern to search.
     * @param CollectionInterface $collection Codebase to search the pattern for.
     */
    public function __construct(string $pattern, CollectionInterface $collection)
    {
        $this->pattern = $pattern;
        $this->collection = $collection;
    }

    /**
     * Find all occurences of the pattern and return it has a code change set filled with warnings.
     * @return CodeChangeSet
     */
    public function findAsWarning(): CodeChangeSet
    {
        $changeset = new CodeChangeSet();

        /** @var ItemInterface $item */
        foreach ($this->collection->iterateItems() as $item) {
            $contents = $item->getContents();

            $occurences = $this->findIn($contents);

            if (!empty($occurences)) {
                $changeset->addWarnings($item->getPath(), $occurences);
            }
        }

        return $changeset;
    }

    /**
     * Loop over a multiline string and try to find all occurnce of the regex pattern.
     * @param  string $content
     * @return array Line numbers where occurence of the pattern can be found.
     */
    private function findIn(string $content): array
    {
        $occurences = [];
        $lines = explode("\n", $content);
        foreach ($lines as $num => $line) {
            if (preg_match($this->pattern, $line)) {
                $occurences[] = [
                    $num+1,
                    preg_replace($this->pattern, '<question>$0</question>', $line)
                ];
            }
        }


        return $occurences;
    }
}
