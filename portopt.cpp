// PortCpp.cpp : Defines the entry point for the console application.
//

#include "stdafx.h"
#include "OptimizationTargets.h"
#include "Optimize.h"

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

	function<float(const vector<float>&)> returnsFunc = ReturnsToStDevRatio;
	//auto returnsFunc = CorrelationToBenchmark(benchmark);
	auto maximize = true;

	auto weights = optimize(rows, returnsFunc, maximize, cout);
}
