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
	float totalReturn;
	float deviation;
	float slopeDeviation;
	float positiveDeviation;
	float negativeDeviation;
	float worstDrawdown;
	std::vector<float> benchmarkCorrelations;

	const static ReturnStats nan;

	bool isSet() const {
		return
			!std::isnan(totalReturn) ||
			!std::isnan(deviation) ||
			!std::isnan(slopeDeviation) ||
			!std::isnan(positiveDeviation) ||
			!std::isnan(negativeDeviation) ||
			!std::isnan(worstDrawdown);
	}
};

struct OptimizationParams
{
	ReturnStats factors;
	ReturnStats targets;
};

ReturnStats GetStats(const std::vector<float>& returns, const std::vector<std::vector<float>>& benchmarks);

float ScaleStats(const ReturnStats& scales, const ReturnStats& results);

std::function<float(const std::vector<float>&)> CustomRatio(const OptimizationParams& params, const std::vector<std::vector<float>>& benchmarks);
