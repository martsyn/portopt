#include <phpcpp.h>
#include <string>
#include <sstream>
#include <map>
#include <functional>
#include <vector>
#include "Optimize.h"
#include "OptimizationTargets.h"

using namespace Php;
using namespace std;

map<string, function<float(const vector<float>&)>> simpleTargets =
{
	{ "ReturnsToStDevRatio", ReturnsToStDevRatio },
	{ "ReturnsToLossStDevRatio", ReturnsToLossStDevRatio },
	{ "WorstDrawdown", WorstDrawdown },
	{ "ReturnToWorstLossRatio", ReturnToWorstLossRatio },
	{ "ReturnToDrawdownRatio", ReturnToDrawdownRatio },
	{ "ReturnToSlopeStDevRatio", ReturnToSlopeStDevRatio },
};

Value optimizeSimpleTarget(Parameters &params)
{
	vector<vector<double>> returns = params[0];
	string target = params[1];
	bool maximize = params[2];

	// transpose and convert to float
	vector<vector<float>> singleReturns(returns[0].size());
	for (size_t i = 0; i < singleReturns.size(); ++i)
	{
		singleReturns[i].resize(returns.size());
		for (size_t j = 0; j < returns.size(); ++j)
			singleReturns[i][j] = returns[j][i];
	}

	function<float(const vector<float>&)> targetFunc;
	auto it = simpleTargets.find(target);
	if (it != simpleTargets.end())
	{
		targetFunc = it->second;
	}
	else if (target == "BenchmarkCorrelation")
	{
		vector<double> benchmark = params[3];
		vector<float> benchmarkSingle(benchmark.begin(), benchmark.end());
		targetFunc = CorrelationToBenchmark(benchmarkSingle);
	}
	else
		throw Exception("Unknown target function");

	auto weights = optimize(singleReturns, targetFunc, maximize, out);
	return (vector<float>) weights;
}

/**
 *  tell the compiler that the get_module is a pure C function
 */
extern "C" {
    
    PHPCPP_EXPORT void *get_module()
    {
        // static(!) Php::Extension object that should stay in memory
        // for the entire duration of the process (that's why it's static)
        static Extension extension("portopt", "1.0");
        
		extension.add("optimizeSimpleTarget", optimizeSimpleTarget,
		{
			ByVal("returns", Type::Array),
			ByVal("target", Type::String),
			ByVal("maximize", Type::Bool),
			ByVal("benchmarks", Type::Array, false),
		});
		
        // return the extension
        return extension;
    }
}
