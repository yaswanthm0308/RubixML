<?php

namespace Rubix\Engine\CrossValidation;

use Rubix\Engine\Estimator;
use Rubix\Engine\Datasets\Labeled;
use Rubix\Engine\Regressors\Regressor;
use Rubix\Engine\Classifiers\Classifier;
use Rubix\Engine\Metrics\Validation\Validation;
use Rubix\Engine\Metrics\Validation\Regression;
use Rubix\Engine\Metrics\Validation\Classification;
use InvalidArgumentException;

class Holdout implements Validator
{
    /**
     * The metric used to score the predictions.
     *
     * @var \Rubix\Engine\Metrics\Validation
     */
    protected $metric;

    /**
     * The holdout ratio. i.e. the ratio of samples to use for validation.
     *
     * @var float
     */
    protected $ratio;

    /**
     * @param  \Rubix\Engine\Metrics\Validation\Validation  $metric
     * @param  float  $ratio
     * @throws \InvalidArgumentException
     * @return void
     */
    public function __construct(Validation $metric, float $ratio = 0.2)
    {
        if ($ratio < 0.01 or $ratio > 1.0) {
            throw new InvalidArgumentException('Holdout ratio must be'
                . ' between 0.01 and 1.0.');
        }

        $this->metric = $metric;
        $this->ratio = $ratio;
    }

    /**
     * Run k training rounds where k is the number of folds. For each round use
     * one fold for testing and the rest to train the model. Return the average
     * score for each training round.
     *
     * @param  \Rubix\Engine\Estimator\Estimator  $estimator
     * @param  \Rubix\Engine\Datasets\Labeled  $dataset
     * @return float
     */
    public function score(Estimator $estimator, Labeled $dataset) : float
    {
        if ($estimator instanceof Classifier) {
            if (!$this->metric instanceof Classfication) {
                throw new InvalidArgumentException('Classification metric only'
                    . ' works on Classifiers, ' . get_class($estimator)
                    . ' found.');
            }
        }

        if ($estimator instanceof Regressor) {
            if (!$this->metric instanceof Regression) {
                throw new InvalidArgumentException('Regression metric only'
                    . ' works on Regressors, ' . get_class($estimator)
                    . ' found.');
            }
        }

        if ($estimator instanceof Clusterer) {
            if (!$this->metric instanceof Clustering) {
                throw new InvalidArgumentException('Clustering metric only'
                    . ' works on Clusterers, ' . get_class($estimator)
                    . ' found.');
            }
        }

        $dataset->randomize();

        if ($estimator instanceof Classifier or $estimator instanceof Clusterer) {
            list($training, $testing) =
                $dataset->stratifiedSplit(1 - $this->ratio);
        } else {
            list($training, $testing) =
                $dataset->split(1 - $this->ratio);
        }

        $estimator->train($training);

        $predictions = $estimator->predict($testing);

        $score = $this->metric->score($predictions, $testing->labels());

        return $score;
    }
}