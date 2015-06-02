#include "stdafx.h"

using namespace std;

void CalcReturns(const vector<vector<float>>& vectors, const vector<float>& weights, vector<float>& returns)
{
	for (size_t i = 0; i < returns.size(); ++i)
	{
		float sum = 0.0f;
		for (size_t j = 0; j < weights.size(); ++j)
			sum += vectors[i][j] * weights[j];
		returns[i] = sum;
	}
}

float Sum(const vector<float>& x)
{
	auto sum = 0.0f;
	for (auto r : x)
		sum += r;
	return sum;
}

float Mean(const vector<float>& x)
{
	return Sum(x) / x.size();
}

// Scale x so that sum of its components=1. Zero sum remains unscaled
void Normalize(vector<float>& x)
{
	auto sum = Sum(x);
	if (sum == 0.0f || sum == 1.0f)
		return;
	const auto scale = 1.0f / sum;
	for (auto& f : x)
		f *= scale;
}


float Correlation(const vector<float>& a, const vector<float>& b)
{
	if (a.size() != b.size())
		throw "mismatched vector sizes";
	float ma = Mean(a);
	float mb = Mean(b);
	float upSum = 0.0f;
	float downSumA = 0.0f, downSumB= 0.0f;
	for (size_t i = 0; i < a.size(); ++i)
	{
		float deva = a[i] - ma;
		float devb = b[i] - mb;
		upSum += deva*devb;
		downSumA += deva*deva;
		downSumB += devb*devb;
	}

	return upSum / sqrt(downSumA*downSumB);
}