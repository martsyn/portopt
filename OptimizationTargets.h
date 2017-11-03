#pragma once

float ReturnsToStDevRatio(const std::vector<float>& returns);
float ReturnsToLossStDevRatio(const std::vector<float>& returns);
float WorstDrawdown(const std::vector<float>& returns);
float ReturnToWorstLossRatio(const std::vector<float>& returns);
float ReturnToDrawdownRatio(const std::vector<float>& returns);
float ReturnToSlopeStDevRatio(const std::vector<float>& returns);
std::function<float(const std::vector<float>&)> CorrelationToBenchmark(const std::vector<float>& benchmark);

struct ReturnStats
{
	float meanReturn;
	float stdDeviation;
	float slopeDeviation;
	float positiveDeviation;
	float negativeDeviation;
	float worstDrawdown;
	float skewness;
	float kurtosis;
	std::vector<float> benchmarkCorrelations;

	const static ReturnStats nan;

	bool isSet() const {
		return
			!std::isnan(meanReturn) ||
			!std::isnan(stdDeviation) ||
			!std::isnan(slopeDeviation) ||
			!std::isnan(positiveDeviation) ||
			!std::isnan(negativeDeviation) ||
			!std::isnan(worstDrawdown)||
			!std::isnan(skewness) ||
			!std::isnan(kurtosis);
	}

	void reset()
	{
		meanReturn = 0;
		stdDeviation = 0;
		slopeDeviation = 0;
		positiveDeviation = 0;
		negativeDeviation = 0;
		worstDrawdown = 0;
		skewness = 0;
		kurtosis = 0;
	}

	void normalize(const int normalizationCount)
	{
		meanReturn *= normalizationCount;
		stdDeviation *= sqrt(static_cast<float>(normalizationCount));
	}
};

struct OptimizationParams
{
	ReturnStats factors;
	ReturnStats targets;
};

void GetStats(const std::vector<float>& returns, const std::vector<std::vector<float>>& benchmarks, ReturnStats &target);

float ScaleStats(const ReturnStats& scales, const ReturnStats& results);

std::function<float(const std::vector<float>&)> CustomRatio(const OptimizationParams& params, const std::vector<std::vector<float>>& benchmarks);

std::function<float(const std::vector<float> &)> CustomVolTarget(const float targetVol);
std::function<float(const std::vector<float> &)> CustomVolTargetNormFactors(const float targetStdev, const float returnFactor, const float skewFactor, const float kurtFactor);
