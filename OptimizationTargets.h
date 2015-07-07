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
};

ReturnStats GetStats(const std::vector<float>& returns, const std::vector<std::vector<float>>& benchmarks);

float ScaleStats(const ReturnStats& scales, const ReturnStats& results);

std::function<float(const std::vector<float>&)> CustomRatio(const ReturnStats& scales, const std::vector<std::vector<float>>& benchmarks);
