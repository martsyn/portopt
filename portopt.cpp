// PortCpp.cpp : Defines the entry point for the console application.
//

#include "stdafx.h"
#include "InitialPointProvider.h"
#include "VectorMath.h"
#include "OptimizationTargets.h"

using namespace std;

vector<vector<float>> ReadMatrix(const char* path)
{
	vector<vector<float>> rows;

	ifstream in(path);
	size_t portCount = 0;
	for (string line; getline(in, line);)
	{
		istringstream str(line);
		vector<float> vals;
		bool first = rows.size() == 0;
		if (!first)
			vals.reserve(portCount);
		float x;
		while (!str.eof())
		{
			str >> x;
			vals.push_back(x);
		}
		if (first)
			portCount = vals.size();
		else if (vals.size() != portCount)
			throw "Mismatching column count";

		rows.push_back(vals);
	}


	return rows;
}

vector<float> ReadVector(const char* path)
{
	vector<float> res;

	ifstream in(path);
	float x;
	while (in >> x)
		res.push_back(x);
	return res;
}


void _tmain()
{
	auto path = "c:\\projects\\dumps\\gallery.tsv";
	cout << "reading " << path << "\n";
	const auto rows = ReadMatrix(path);
	size_t portCount = rows[0].size();
	size_t retCount = rows.size();
	cout << "read " << portCount << " portfolios with " << retCount << " points each\n";

	const auto benchmarkPath = "c:\\projects\\dumps\\spx.tsv";
	cout << "reading " << benchmarkPath << "\n";
	auto benchmark = ReadVector(benchmarkPath);
	cout << "read " << benchmark.size() << "\n";

	if (benchmark.size() != retCount)
	{
		cerr << "mismatching counts\n";
		exit(1);
	}

	function<float(const vector<float>&)> returnsFunc = ReturnToSlopeStDevRatio;
	//auto returnsFunc = CorrelationToBenchmark(benchmark);

	auto maximize = true;
	vector<float> weights(portCount);

	const auto stepCount = 4u;
	InitialPointProvider ini(portCount, stepCount);

	vector<float> best(retCount);
	const float worstRes = maximize ? -numeric_limits<float>::infinity() : numeric_limits<float>::infinity();
	float bestRes = worstRes;

	vector<float> returns(retCount);
	auto func = [&](const vector<float>& ws)
	{
		CalcReturns(rows, ws, returns);
		return returnsFunc(returns);
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

			cout << "found new best " << bestRes << " at ";
			for (auto w : best)
				cout << w << ' ';
			cout << "\n";
		}
	}

	cout << count << " iterations\n";

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

				cout << "found improvement step=" << step << " port=" << i << " new best=" << nextRes << "\n";
			}
		}

		if (maximize 
			? nextRes > bestRes
			: nextRes < bestRes)
		{
			best = next;
			bestRes = nextRes;
			cout << "shifted " << step << " new best="<<bestRes << " :: weights: ";
			for (auto w : best)
				cout << w << ' ';
			cout << "\n";
		}
		else
			step *= 0.5f;
	}

}
