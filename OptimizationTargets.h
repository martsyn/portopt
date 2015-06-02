#pragma once

float ReturnsToStDevRatio(const std::vector<float>& returns);
float ReturnsToLossStDevRatio(const std::vector<float>& returns);
float NegatedDrawdown(const std::vector<float>& returns);
float ReturnToWorstLossRatio(const std::vector<float>& returns);
float ReturnToDrawdownRatio(const std::vector<float>& returns);
float ReturnToSlopeStDevRatio(const std::vector<float>& returns);
std::function<float(const std::vector<float>&)> CorrelationToBenchmark(const std::vector<float>& benchmark);
