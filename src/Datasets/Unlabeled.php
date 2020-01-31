<?php

namespace Rubix\ML\Datasets;

use Rubix\ML\Other\Helpers\Console;
use Rubix\ML\Kernels\Distance\Distance;
use Rubix\ML\Other\Specifications\SamplesAreCompatibleWithDistance;
use InvalidArgumentException;
use Generator;

use function count;
use function get_class;
use function array_slice;

use const Rubix\ML\PHI;

/**
 * Unlabeled
 *
 * Unlabeled datasets are used to train unsupervised learners and for feeding unknown
 * samples into an estimator to make predictions during inference. As their name implies,
 * they do not require a corresponding label for each sample.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class Unlabeled extends Dataset
{
    /**
     * Build a new unlabeled dataset with validation.
     *
     * @param array[] $samples
     * @return self
     */
    public static function build(array $samples = []) : self
    {
        return new self($samples, true);
    }

    /**
     * Build a new unlabeled dataset foregoing validation.
     *
     * @param array[] $samples
     * @return self
     */
    public static function quick(array $samples = []) : self
    {
        return new self($samples, false);
    }

    /**
     * Build a dataset with the rows from an iterable data table.
     *
     * @param \Traversable<array> $iterator
     * @return self
     */
    public static function fromIterator(iterable $iterator) : self
    {
        return self::build(iterator_to_array($iterator));
    }

    /**
     * Stack a number of datasets on top of each other to form a single
     * dataset.
     *
     * @param \Rubix\ML\Datasets\Dataset[] $datasets
     * @throws \InvalidArgumentException
     * @return self
     */
    public static function stack(array $datasets) : self
    {
        $samples = [];

        foreach ($datasets as $dataset) {
            if (!$dataset instanceof Dataset) {
                throw new InvalidArgumentException('Dataset must be an'
                    . ' instance of Dataset, ' . get_class($dataset)
                    . ' given.');
            }

            $samples[] = $dataset->samples();
        }

        return self::quick(array_merge(...$samples));
    }

    /**
     * Return a dataset containing only the first n samples.
     *
     * @param int $n
     * @throws \InvalidArgumentException
     * @return self
     */
    public function head(int $n = 10) : self
    {
        if ($n < 1) {
            throw new InvalidArgumentException('The number of samples'
                . " cannot be less than 1, $n given.");
        }

        return $this->slice(0, $n);
    }

    /**
     * Return a dataset containing only the last n samples.
     *
     * @param int $n
     * @throws \InvalidArgumentException
     * @return self
     */
    public function tail(int $n = 10) : self
    {
        if ($n < 1) {
            throw new InvalidArgumentException('The number of samples'
                . " cannot be less than 1, $n given.");
        }

        return $this->slice(-$n, $this->numRows());
    }

    /**
     * Take n samples from this dataset and return them in a new dataset.
     *
     * @param int $n
     * @throws \InvalidArgumentException
     * @return self
     */
    public function take(int $n = 1) : self
    {
        if ($n < 1) {
            throw new InvalidArgumentException('The number of samples'
                . " cannot be less than 1, $n given.");
        }

        return $this->splice(0, $n);
    }

    /**
     * Leave n samples on this dataset and return the rest in a new dataset.
     *
     * @param int $n
     * @throws \InvalidArgumentException
     * @return self
     */
    public function leave(int $n = 1) : self
    {
        if ($n < 1) {
            throw new InvalidArgumentException('The number of samples'
                . " cannot be less than 1, $n given.");
        }

        return $this->splice($n, $this->numRows());
    }

    /**
     * Return an n size portion of the dataset in a new dataset.
     *
     * @param int $offset
     * @param int $n
     * @return self
     */
    public function slice(int $offset, int $n) : self
    {
        return self::quick(array_slice($this->samples, $offset, $n));
    }

    /**
     * Remove a size n chunk of the dataset starting at offset and return it in
     * a new dataset.
     *
     * @param int $offset
     * @param int $n
     * @return self
     */
    public function splice(int $offset, int $n) : self
    {
        return self::quick(array_splice($this->samples, $offset, $n));
    }

    /**
     * Prepend a dataset to this dataset.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     * @throws \InvalidArgumentException
     * @return \Rubix\ML\Datasets\Dataset
     */
    public function prepend(Dataset $dataset) : Dataset
    {
        if ((!$dataset->empty() and !$this->empty()) and $dataset->numColumns() !== $this->numColumns()) {
            throw new InvalidArgumentException('Can only prepend with dataset'
                . ' that has the same number of columns.');
        }

        return self::quick(array_merge($dataset->samples(), $this->samples));
    }

    /**
     * Append a dataset to this dataset.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     * @throws \InvalidArgumentException
     * @return \Rubix\ML\Datasets\Dataset
     */
    public function append(Dataset $dataset) : Dataset
    {
        if ((!$dataset->empty() and !$this->empty()) and $dataset->numColumns() !== $this->numColumns()) {
            throw new InvalidArgumentException('Can only append with dataset'
                . ' that has the same number of columns.');
        }

        return self::quick(array_merge($this->samples, $dataset->samples()));
    }

    /**
     * Drop the row at the given index.
     *
     * @param int $index
     * @return self
     */
    public function dropRow(int $index) : self
    {
        return $this->dropRows([$index]);
    }

    /**
     * Drop the rows at the given indices.
     *
     * @param int[] $indices
     * @throws \InvalidArgumentException
     * @return self
     */
    public function dropRows(array $indices) : self
    {
        foreach ($indices as $index) {
            unset($this->samples[$index]);
        }

        $this->samples = array_values($this->samples);

        return $this;
    }

    /**
     * Randomize the dataset in place and return self for chaining.
     *
     * @return self
     */
    public function randomize() : self
    {
        shuffle($this->samples);

        return $this;
    }

    /**
     * Filter the rows of the dataset using the values of a feature column as the
     * argument to a callback.
     *
     * @param int $index
     * @param callable $callback
     * @return self
     */
    public function filterByColumn(int $index, callable $callback) : self
    {
        $samples = [];

        foreach ($this->samples as $sample) {
            if ($callback($sample[$index])) {
                $samples[] = $sample;
            }
        }

        return self::quick($samples);
    }

    /**
     * Sort the dataset in place by a column in the sample matrix.
     *
     * @param int $index
     * @param bool $descending
     * @return self
     */
    public function sortByColumn(int $index, bool $descending = false) : self
    {
        $column = $this->column($index);

        array_multisort($column, $this->samples, $descending ? SORT_DESC : SORT_ASC);

        return $this;
    }

    /**
     * Split the dataset into two stratified subsets with a given ratio of samples.
     *
     * @param float $ratio
     * @throws \InvalidArgumentException
     * @return self[]
     */
    public function split(float $ratio = 0.5) : array
    {
        if ($ratio <= 0. or $ratio >= 1.) {
            throw new InvalidArgumentException('Split ratio must be strictly'
                . " between 0 and 1, $ratio given.");
        }

        $n = (int) floor($ratio * $this->numRows());

        return [
            self::quick(array_slice($this->samples, 0, $n)),
            self::quick(array_slice($this->samples, $n)),
        ];
    }

    /**
     * Fold the dataset k - 1 times to form k equal size datasets.
     *
     * @param int $k
     * @throws \InvalidArgumentException
     * @return self[]
     */
    public function fold(int $k = 3) : array
    {
        if ($k < 2) {
            throw new InvalidArgumentException('Cannot create less than 2'
                . " folds, $k given.");
        }

        $samples = $this->samples;

        $n = (int) floor(count($samples) / $k);

        $folds = [];

        while (count($folds) < $k) {
            $folds[] = self::quick(array_splice($samples, 0, $n));
        }

        return $folds;
    }

    /**
     * Generate a collection of batches of size n from the dataset. If there are
     * not enough samples to fill an entire batch, then the dataset will contain
     * as many samples as possible.
     *
     * @param int $n
     * @return self[]
     */
    public function batch(int $n = 50) : array
    {
        return array_map([self::class, 'quick'], array_chunk($this->samples, $n));
    }

    /**
     * Partition the dataset into left and right subsets by a specified feature
     * column. The dataset is split such that, for categorical values, the left
     * subset contains all samples that match the value and the right side
     * contains samples that do not match. For continuous values, the left side
     * contains all the  samples that are less than the target value, and the
     * right side contains the samples that are greater than or equal to the
     * value.
     *
     * @param int $column
     * @param string|int|float $value
     * @throws \InvalidArgumentException
     * @return self[]
     */
    public function partition(int $column, $value) : array
    {
        $left = $right = [];

        if ($this->columnType($column)->isContinuous()) {
            foreach ($this->samples as $sample) {
                if ($sample[$column] < $value) {
                    $left[] = $sample;
                } else {
                    $right[] = $sample;
                }
            }
        } else {
            foreach ($this->samples as $sample) {
                if ($sample[$column] === $value) {
                    $left[] = $sample;
                } else {
                    $right[] = $sample;
                }
            }
        }

        return [self::quick($left), self::quick($right)];
    }

    /**
     * Partition the dataset into left and right subsets based on their distance
     * between two centroids.
     *
     * @param (string|int|float)[] $leftCentroid
     * @param (string|int|float)[] $rightCentroid
     * @param \Rubix\ML\Kernels\Distance\Distance $kernel
     * @throws \InvalidArgumentException
     * @return self[]
     */
    public function spatialPartition(array $leftCentroid, array $rightCentroid, Distance $kernel)
    {
        if (count($leftCentroid) !== count($rightCentroid)) {
            throw new InvalidArgumentException('Dimensionality mismatch between'
                . ' left and right centroids.');
        }

        SamplesAreCompatibleWithDistance::check($this, $kernel);

        $left = $right = [];

        foreach ($this->samples as $sample) {
            $lDistance = $kernel->compute($sample, $leftCentroid);
            $rDistance = $kernel->compute($sample, $rightCentroid);

            if ($lDistance < $rDistance) {
                $left[] = $sample;
            } else {
                $right[] = $sample;
            }
        }

        return [self::quick($left), self::quick($right)];
    }

    /**
     * Generate a random subset without replacement.
     *
     * @param int $n
     * @throws \InvalidArgumentException
     * @return self
     */
    public function randomSubset(int $n) : self
    {
        if ($n < 1) {
            throw new InvalidArgumentException('Cannot generate a'
                . " subset of less than 1 sample, $n given.");
        }

        if ($n > $this->numRows()) {
            throw new InvalidArgumentException('Cannot generate subset'
                . " of more than {$this->numRows()}, $n given.");
        }

        $indices = array_rand($this->samples, $n);

        $indices = is_array($indices) ? $indices : [$indices];
        
        $samples = [];

        foreach ($indices as $index) {
            $samples[] = $this->samples[$index];
        }

        return self::quick($samples);
    }

    /**
     * Generate a random subset with replacement.
     *
     * @param int $n
     * @throws \InvalidArgumentException
     * @return self
     */
    public function randomSubsetWithReplacement(int $n) : self
    {
        if ($n < 1) {
            throw new InvalidArgumentException('Cannot generate a subset of'
                . " less than 1 sample, $n given.");
        }

        $maxIndex = $this->numRows() - 1;

        $subset = [];

        while (count($subset) < $n) {
            $subset[] = $this->samples[rand(0, $maxIndex)];
        }

        return self::quick($subset);
    }

    /**
     * Generate a random weighted subset with replacement.
     *
     * @param int $n
     * @param (int|float)[] $weights
     * @throws \InvalidArgumentException
     * @return self
     */
    public function randomWeightedSubsetWithReplacement(int $n, array $weights) : self
    {
        if ($n < 1) {
            throw new InvalidArgumentException('Cannot generate a'
                . " subset of less than 1 sample, $n given.");
        }

        if (count($weights) !== count($this->samples)) {
            throw new InvalidArgumentException('The number of weights'
                . ' must be equal to the number of samples in the'
                . ' dataset, ' . count($this->samples) . ' needed'
                . ' but ' . count($weights) . ' given.');
        }

        $total = array_sum($weights);
        $max = (int) round($total * PHI);

        $subset = [];

        while (count($subset) < $n) {
            $delta = rand(0, $max) / PHI;

            foreach ($weights as $index => $weight) {
                $delta -= $weight;

                if ($delta <= 0.) {
                    $subset[] = $this->samples[$index];
                    
                    break 1;
                }
            }
        }

        return self::quick($subset);
    }

    /**
     * Remove duplicate rows from the dataset.
     *
     * @return self
     */
    public function deduplicate() : self
    {
        $this->samples = array_values(array_unique($this->samples, SORT_REGULAR));

        return $this;
    }

    /**
     * Return the dataset object as a data table array.
     *
     * @return array[]
     */
    public function toArray() : array
    {
        return $this->samples;
    }

    /**
     * Return a sample from the dataset given by index.
     *
     * @param mixed $index
     * @throws \InvalidArgumentException
     * @return array[]
     */
    public function offsetGet($index) : array
    {
        if (isset($this->samples[$index])) {
            return $this->samples[$index];
        }

        throw new InvalidArgumentException("Row at offset $index not found.");
    }

    /**
     * Get an iterator for the samples in the dataset.
     *
     * @return \Generator<array>
     */
    public function getIterator() : Generator
    {
        yield from $this->samples;
    }

    /**
     * Return a string representation of the first few rows of the dataset.
     *
     * @return string
     */
    public function __toString() : string
    {
        [$tRows, $tCols] = Console::size();

        $m = (int) floor($tRows / 2) + 2;
        $n = (int) floor($tCols / (3 + Console::TABLE_CELL_WIDTH));

        $m = min($this->numRows(), $m);
        $n = min($this->numColumns(), $n);

        $header = [];

        for ($column = '0'; $column < $n; ++$column) {
            $header[] = "Column $column";
        }

        $table = array_slice($this->samples, 0, $m);

        foreach ($table as $i => &$row) {
            $row = array_slice($row, 0, $n);
        }

        $table = array_merge([$header], $table);

        return Console::table($table);
    }
}
