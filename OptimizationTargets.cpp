#include "stdafx.h"
#include "VectorMath.h"

using namespace std;

float ReturnsToStDevRatio(const vector<float>& returns)
{
	auto sum = Sum(returns);
	auto mean = sum / returns.size();
	auto devSqSum = 0.0f;
	for (auto f : returns)
	{
		auto dev = f - mean;
		devSqSum += dev*dev;
	}
	return sum / sqrt(devSqSum / returns.size());
}

float ReturnsToLossStDevRatio(const vector<float>& returns)
{
	float sum = 0.0f;
	float lossSum = 0.0f;
	size_t lossCount = 0;

	for (auto r : returns)
	{
		sum += r;
		if (r < 0)
		{
			lossSum -= r;
			++lossCount;
		}
	}

	auto lossMean = lossSum / lossCount;
	auto devSqSum = 0.0f;
	for (auto r : returns)
		if (r < 0)
		{
			auto dev = -r - lossMean;
			devSqSum += dev*dev;
		}
	return sum / sqrt(devSqSum / lossCount);
}

float WorstDrawdown(const vector<float>& returns)
{
	auto worst = 0.0f;
	auto current = 0.0f;

	for (auto r : returns)
	{
		current += r;
		if (worst < current)
			worst = current;
		if (current > 0.0f)
			current = 0.0f;
	}

	return worst;
}

float ReturnToWorstLossRatio(const vector<float>& returns)
{
	auto sum = 0.0f;
	auto worst = 0.0f;
	for (auto r : returns)
	{
		sum += r;
		if (-r > worst)
			worst = -r;
	}
	return sum/worst;
}

float ReturnToDrawdownRatio(const vector<float>& returns)
{
	auto sum = 0.0f;
	auto worst = 0.0f;
	auto current = 0.0f;

	for (auto r : returns)
	{
		sum += r;
		current += r;
		if (worst < current)
			worst = current;
		if (current > 0.0f)
			current = 0.0f;
	}

	return worst < 0.0f
		? -sum / worst
		: numeric_limits<float>::infinity();
}

float ReturnToSlopeStDevRatio(const vector<float>& returns)
{
	auto sum = 0.0f;
	for (auto r : returns)
		sum += r;

	auto slope = sum / returns.size();
	auto cum = 0.0f;
	auto devSum = 0.0f;
	for (size_t i = 0; i < returns.size(); ++i)
	{
		cum += returns[i];
		auto dev = slope*(i + 1) - cum;
		devSum += dev*dev;
	}

	return sum / sqrt(devSum);
}

function<float(const vector<float>&)> CorrelationToBenchmark(const vector<float>& benchmark)
{
	return [=](const vector<float>& returns)
	{
		return Correlation(returns, benchmark);
	};
}