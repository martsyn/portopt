#include "stdafx.h"

#include "VectorMath.h"

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

void CalcReturnsNoReinvestment(const vector<vector<float>>& vectors, const vector<float>& weights, vector<float>& returns)
{
  vector<float> allocations = weights;
  float prevSum = 1.0f;
	
  for (size_t i = 0; i < returns.size(); ++i)
	{
    auto allocSum = Sum(allocations);

		float sum = 0.0f;
    for (size_t j = 0; j < weights.size(); ++j)
    {
      sum += vectors[i][j] * allocations[j];
      allocations[j] *= 1 + vectors[i][j];
    }
		returns[i] = sum/allocSum - 1;
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

std::ostream& operator<<(std::ostream& os, const std::vector<float>& v)
{
  os << '[' << v.size() << "] {";
  for (auto i = v.begin(); i != v.end(); ++i)
  {
    if (i != v.begin())
      os << ", ";
    os << *i;
  }
  os << '}';
  return os;
}
