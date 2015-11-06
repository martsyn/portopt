#include <phpcpp.h>
#include <string>
#include <sstream>
#include <map>
#include <functional>
#include <vector>
#include "Optimize.h"
#include "OptimizationTargets.h"
#include "Constraint.h"
#include "onullstream.h"

using namespace Php;
using namespace std;

/*
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
*/

map<string, float ReturnStats::*> targetFieldMap = 
{
  {"return", &ReturnStats::totalReturn},
  {"volatility", &ReturnStats::deviation },
  {"slopeDeviation", &ReturnStats::slopeDeviation },
  {"posDeviation", &ReturnStats::positiveDeviation },
  {"negDeviation", &ReturnStats::negativeDeviation },
  {"drawdown", &ReturnStats::worstDrawdown },
};

class OptimizationConstraintPhp : public Base
{
  bool _required;
  float _min;
  float _max;
public:
  OptimizationConstraintPhp() :
    _required(false), _min(0), _max(1)
  {
  }

  OptimizationConstraintPhp(const OptimizationConstraintPhp&) = delete;
  
  virtual ~OptimizationConstraintPhp()
  {
  }

  void __construct(Parameters &params)
  {
    if (params.size() >= 1)
      _required = params[0];
    if (params.size() >= 2)
      _min = static_cast<double>(params[1]);
    if (params.size() >= 3)
      _max = static_cast<double>(params[2]);
  }

  Value __toString() const
  {
    stringstream o;
    o << "constraint(" << (_required ? "required" : "optional") << " [" << _min << ',' << _max << "])";
    return o.str();
  }

  void setRequired(const Value &value) { _required = value; }
  Value getRequired() const { return _required; }

  void setMin(const Value &value) { _min = static_cast<double>(value); }
  Value getMin() const { return static_cast<double>(_min); }

  void setMax(const Value &value) { _max = static_cast<double>(value); }
  Value getMax() const { return static_cast<double>(_max); }

  operator Constraint() const
  {
    return Constraint(_required, _min, _max);
  }
};

Value optimizeCustomTarget(Parameters &params)
{
  vector<Object> constraintsPhp = params[0];
  vector<vector<double>> returns = params[1];
  map<string, double> targets = params[2];

  vector<Constraint> constraints;//(returns.size(), Constraint(false, 0, 1));
  constraints.reserve(constraintsPhp.size());
  for (const Object &c : constraintsPhp)
    constraints.push_back(*dynamic_cast<OptimizationConstraintPhp*>(c.implementation()));

  // transpose and convert to float
  vector<vector<float>> singleReturns(returns[0].size());
  for (size_t i = 0; i < singleReturns.size(); ++i)
  {
    singleReturns[i].resize(returns.size());
    for (size_t j = 0; j < returns.size(); ++j)
      singleReturns[i][j] = returns[j][i];
  }

  ReturnStats scales{};
  for (auto& tp : targets) {
    auto mpp = targetFieldMap.find(tp.first);
    if (mpp != targetFieldMap.end())
    {
      scales.*(mpp->second) = tp.second;
//      out << mpp->first << "=" << tp.second << "\n";
    }
    else
      warning << "Unknown target field " << tp.first << "=" << tp.second << flush;
  }

  vector<std::vector<float>> benchmarks{};

  auto weights = optimize(constraints, singleReturns, CustomRatio(scales, benchmarks), true, onullstream::instance());
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
/*
    extension.add("optimizeSimpleTarget", optimizeSimpleTarget,
    {
      ByVal("returns", Type::Array),
      ByVal("target", Type::String),
      ByVal("maximize", Type::Bool),
      ByVal("benchmarks", Type::Array, false),
    });
*/
    extension.add("optimizeCustomTarget", optimizeCustomTarget,
    {
      ByVal("constraints", Type::Array),
      ByVal("returns", Type::Array),
      ByVal("targets", Type::Array),
      ByVal("benchmarks", Type::Array, false),
    });

    Php::Class<OptimizationConstraintPhp> optimizationConstraintClass("OptimizationConstraint");

    optimizationConstraintClass.method("__construct", &OptimizationConstraintPhp::__construct,
    {
      ByVal("required", Type::Bool),
      ByVal("min", Type::Float),
      ByVal("max", Type::Float),
    });

    optimizationConstraintClass.method("__toString", &OptimizationConstraintPhp::__toString);

    optimizationConstraintClass.property("required", &OptimizationConstraintPhp::getRequired, &OptimizationConstraintPhp::setRequired);
    optimizationConstraintClass.property("min", &OptimizationConstraintPhp::getMin, &OptimizationConstraintPhp::setMin);
    optimizationConstraintClass.property("max", &OptimizationConstraintPhp::getMax, &OptimizationConstraintPhp::setMax);
    optimizationConstraintClass.property("boo", "moo", Public);


    extension.add(move(optimizationConstraintClass));

    // return the extension
    return extension;
  }
}
