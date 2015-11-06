#include "stdafx.h"
#include "InitialPointProvider.h"
#include "VectorMath.h"
#include <cassert>

using namespace std;

// Scale x so that sum of its components=1. Zero sum remains unscaled
void normalize(vector<float>& x, const vector<Constraint> constraints);

vector<float> optimize(const vector<Constraint>& constraints, const vector<vector<float>>& returns, function<float(const vector<float>&)> returnsFunc, bool maximize, ostream& log)
{
	size_t portCount = returns[0].size();
	size_t retCount = returns.size();

	vector<float> weights(portCount);
  const int digits = 4;
	const size_t stepCount = 4u;

  InitialPointProvider ini(constraints, weights, digits, stepCount);

	vector<float> best(retCount);
	const float worstRes = maximize ? -numeric_limits<float>::infinity() : numeric_limits<float>::infinity();
	float bestRes = worstRes;

	vector<float> totals(retCount);
	auto func = [&](const vector<float>& ws)
	{
		CalcReturns(returns, ws, totals);
		return returnsFunc(totals);
	};

	auto count = 0u;
	while (ini.getNext())
	{
		++count;
		float res = func(weights);

    //log << "\t testing next " << res << " at ";
    //for (auto w : weights)
    //  log << w << ' ';
    //log << "\n";


		if (maximize
			? res > bestRes
			: res < bestRes)
		{
			best = weights;
			bestRes = res;

			log << "found new best " << bestRes << " at ";
			for (auto w : best)
				log << w << ' ';
			log << "\n";
		}
	}

	log << count << " iterations\n";

	// optimization
  const float accuracy = pow(10.0f, -digits - 1);
	auto step = 0.5f / stepCount;
	auto stepUp = vector<float>(portCount);
	auto stepDown = vector<float>(portCount);
	auto next = vector<float>(portCount);
	float nextRes;

	while (step > accuracy)
	{
		nextRes = bestRes;

		for (size_t i = 0; i < portCount; ++i)
		{
      auto& constraint = constraints[i];
      auto min = constraint.min();
      auto max = constraint.max();

			float stepUpRes;
		  if (best[i] < max && best[i] >= min)
			{
				stepUp = best;
				stepUp[i] += step;
				if (stepUp[i] > max)
					stepUp[i] = max;
				normalize(stepUp, constraints);
				stepUpRes = func(stepUp);
			}
			else
				stepUpRes = worstRes;

			float stepDownRes;
			if (best[i] > min)
			{
				stepDown = best;
				stepDown[i] -= step;
				if (stepDown[i] < min)
					stepDown[i] = min;
				normalize(stepDown, constraints);
				stepDownRes = func(stepDown);
			}
			else
				stepDownRes = worstRes;

			if (maximize
				? stepUpRes > nextRes || stepDownRes > nextRes
				: stepUpRes < nextRes || stepDownRes < nextRes)
			{
				if (maximize
					? stepUpRes > stepDownRes
					: stepUpRes < stepDownRes)
				{
					next = stepUp;
					nextRes = stepUpRes;
				}
				else
				{
					next = stepDown;
					nextRes = stepDownRes;
				}

				log << "found improvement step=" << step << " port=" << i << " new best=" << nextRes << "\n";
			}
		}

		if (maximize
			? nextRes > bestRes
			: nextRes < bestRes)
		{
			best = next;
			bestRes = nextRes;
			log << "shifted " << step << " new best=" << bestRes << " :: weights: ";
			for (auto w : best)
				log << w << ' ';
			log << "\n";
		}
		else
			step *= 0.5f;
	}

	return best;
}

void normalize(vector<float>& x, const vector<Constraint> constraints)
{
  auto sum = Sum(x);
  if (sum == 0.0f || sum == 1.0f)
    return;

  bool decrease = sum > 1;
  auto overflow = abs(sum - 1);
  
  // find nearest to constraint value
  // adjust all other values by that smallest difference
  // repeat until overflow is matched
  bool matched;
  do
  {
    auto minDiff = numeric_limits<float>::infinity();
    size_t count = 0;
    for (size_t i = 0; i < x.size(); ++i)
    {
      auto diff = decrease ? x[i] - constraints[i].min() : constraints[i].max() - x[i];
      if (diff > 0) {
        if (diff < minDiff)
          minDiff = diff;
        ++count;
      }
    }
    assert(count > 0);

    auto diff = overflow / count;
    matched = diff <= minDiff;
    if (!matched)
    {
      diff = minDiff;
      overflow -= diff*count;
    }
    for (size_t i = 0; i < x.size(); ++i)
      if (decrease ? x[i] > constraints[i].min() : x[i] < constraints[i].max())
        x[i] += decrease ? -diff : diff;
  } while (!matched);
}
