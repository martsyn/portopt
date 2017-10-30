#pragma once

#include "VectorMath.h"
#include "Constraint.h"

struct EfficientFrontierPoint
{
	const NormStats stats;
	const std::vector<float> weights;

	EfficientFrontierPoint(const NormStats& stats, const std::vector<float>& weights)
		: stats(stats),
		weights(weights)
	{
	}
};

std::vector<float> optimize(const std::vector<Constraint>& constraints, const std::vector<std::vector<float>>& returns, std::function<float(const std::vector<float>&)> returnsFunc, bool maximize, std::ostream& log);
std::vector<EfficientFrontierPoint> buildEfficientFrontier(const std::vector<Constraint> &constraints, const std::vector<std::vector<float>> &returns, size_t normalization_count, const float returnFactor, const float skewFactor, const float kurtFactor);
