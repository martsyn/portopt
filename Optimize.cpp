#include "stdafx.h"
#include "InitialPointProvider.h"
#include "VectorMath.h"

using namespace std;

vector<float> optimize(vector<vector<float>> returns, function<float(const vector<float>&)> returnsFunc, bool maximize)
{
	size_t portCount = returns[0].size();
	size_t retCount = returns.size();

	vector<float> weights(portCount);
	const auto stepCount = 4u;
	InitialPointProvider ini(portCount, stepCount);

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
	while (ini.Pull(weights))
	{
		++count;
		float res = func(weights);

		if (maximize
			? res > bestRes
			: res < bestRes)
		{
			best = weights;
			bestRes = res;

//			cout << "found new best " << bestRes << " at ";
//			for (auto w : best)
//				cout << w << ' ';
//			cout << "\n";
		}
	}

//	cout << count << " iterations\n";

	// optimization
	const float accuracy = 0.0001f;
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
			float stepUpRes;
			if (best[i] < 1.0f)
			{
				stepUp = best;
				stepUp[i] += step;
				if (stepUp[i] > 1.0f)
					stepUp[i] = 1.0f;
				Normalize(stepUp);
				stepUpRes = func(stepUp);
			}
			else
				stepUpRes = worstRes;

			float stepDownRes;
			if (best[i] > 0.0f)
			{
				stepDown = best;
				stepDown[i] -= step;
				if (stepDown[i] < 0.0f)
					stepDown[i] = 0.0f;
				Normalize(stepDown);
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

//				cout << "found improvement step=" << step << " port=" << i << " new best=" << nextRes << "\n";
			}
		}

		if (maximize
			? nextRes > bestRes
			: nextRes < bestRes)
		{
			best = next;
			bestRes = nextRes;
//			cout << "shifted " << step << " new best=" << bestRes << " :: weights: ";
//			for (auto w : best)
//				cout << w << ' ';
//			cout << "\n";
		}
		else
			step *= 0.5f;
	}

	return weights;
}
