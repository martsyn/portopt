#include <phpcpp.h>
#include <string>
#include <sstream>
#include <map>
#include <functional>
#include <vector>
#include <cmath>
#include "Optimize.h"
#include "OptimizationTargets.h"
#include "Constraint.h"
#include "onullstream.h"
#include "VectorMath.h"

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
		if (params.size() >= 4)
			_max = static_cast<double>(params[3]);
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

void statsFromMap(const map<string, double> factors, ReturnStats &stats) {
  for (auto &fp : factors) {
    auto mpp = targetFieldMap.find(fp.first);
    if (mpp != targetFieldMap.end()) {
      stats.*(mpp->second) = fp.second;
      //      out << mpp->first << "=" << tp.second << "\n";
    } else
      warning << "Unknown target field " << fp.first << "=" << fp.second
              << flush;
  }
}

Value optimizeCustomTarget(Parameters &params) {
  vector<Object> constraintsPhp = params[0];
  vector<vector<double>> returns = params[1];
  map<string, double> factorMap = params[2];
  map<string, double> targetMap;
  if (params.size() > 3) {
	  targetMap = params[3];
	  warning << "Got targets: ";
	  for (const auto &p : targetMap) {
		  warning << p.first << "=" << p.second << "  ";
	  }
  }

  auto retCount = returns[0].size();
  auto portCount = returns.size();

  if (constraintsPhp.size() != portCount)
	  throw "Mismatching returns and constraints count";

  vector<Constraint> constraints;//(returns.size(), Constraint(false, 0, 1));
  constraints.reserve(constraintsPhp.size());
  for (const Object &c : constraintsPhp)
    constraints.push_back(
        *dynamic_cast<OptimizationConstraintPhp *>(c.implementation()));

  // transpose and convert to float
  vector<vector<float>> singleReturns(retCount);
  for (size_t i = 0; i < retCount; ++i) {
    singleReturns[i].resize(portCount);
    for (size_t j = 0; j < portCount; ++j)
      singleReturns[i][j] = returns[j][i];
  }

  ReturnStats factors, targets = ReturnStats::nan;
  statsFromMap(factorMap, factors);
  statsFromMap(targetMap, targets);

  //warning << "totalReturn=" << targets.totalReturn << " :: ";
  //warning << "deviation=" << targets.deviation << " :: ";
  //warning << "slopeDeviation=" << targets.slopeDeviation << " :: ";
  //warning << "positiveDeviation=" << targets.positiveDeviation << " :: ";
  //warning << "negativeDeviation=" << targets.negativeDeviation << " :: ";
  //warning << "worstDrawdown =" << targets.worstDrawdown << " :: ";

  //warning.flush();

  vector<std::vector<float>> benchmarks{};

  try {
    auto returnsFunc =
        CustomRatio(OptimizationParams{factors, targets}, benchmarks);
    auto weights =
		optimize(constraints, singleReturns, returnsFunc,
                 true, onullstream::instance());

	vector<float> portReturns(retCount);
	CalcReturns(singleReturns, weights, portReturns);
	//auto res = returnsFunc(portReturns);
	auto sum = 0.f;
	for (auto w : weights)
		sum += w;
	//warning << "result: " << res << "\nweights: " << weights << " (sum=" << sum << ")\n";

	auto stats = GetStats(portReturns, {});

	warning << "totalReturn: " << stats.totalReturn;
	//warning << "deviation: " << stats.deviation;
	//warning << "slopeDeviation: " << stats.slopeDeviation;
	//warning << "positiveDeviation: " << stats.positiveDeviation;
	//warning << "negativeDeviation: " << stats.negativeDeviation;
	//warning << "worstDrawdown: " << stats.worstDrawdown;
	warning.flush();

	return (vector<float>)weights;
  } catch (const char *x) {
    warning << x << std::flush;
    throw Exception(x);
  }
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
      ByVal("factors", Type::Array),
      ByVal("targets", Type::Array, false),
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

    extension.add(move(optimizationConstraintClass));

    // return the extension
    return extension;
  }
}
