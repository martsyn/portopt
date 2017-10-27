#include "stdafx.h"
#include "VectorMath.h"
#include "OptimizationTargets.h"

using namespace std;

const ReturnStats ReturnStats::nan = {
	nanf(""),
	nanf(""),
	nanf(""),
	nanf(""),
	nanf(""),
	nanf(""),
};

float ReturnsToStDevRatio(const vector<float> &returns) {
  auto sum = Sum(returns);
  auto mean = sum / returns.size();
  auto devSqSum = 0.0f;
  for (auto f : returns) {
    auto dev = f - mean;
    devSqSum += dev * dev;
  }
  return sum / sqrt(devSqSum / returns.size());
}

float ReturnsToLossStDevRatio(const vector<float> &returns) {
  float sum = 0.0f;
  float lossSum = 0.0f;
  size_t lossCount = 0;

  for (auto r : returns) {
    sum += r;
    if (r < 0) {
      lossSum -= r;
      ++lossCount;
    }
  }

  auto lossMean = lossSum / lossCount;
  auto devSqSum = 0.0f;
  for (auto r : returns)
    if (r < 0) {
      auto dev = -r - lossMean;
      devSqSum += dev * dev;
    }
  return sum / sqrt(devSqSum / lossCount);
}

float WorstDrawdown(const vector<float> &returns) {
  auto worst = 0.0f;
  auto current = 0.0f;

  for (auto r : returns) {
    current += r;
    if (worst < current)
      worst = current;
    if (current > 0.0f)
      current = 0.0f;
  }

  return worst;
}

float ReturnToWorstLossRatio(const vector<float> &returns) {
  auto sum = 0.0f;
  auto worst = 0.0f;
  for (auto r : returns) {
    sum += r;
    if (-r > worst)
      worst = -r;
  }
  return sum / worst;
}

float ReturnToDrawdownRatio(const vector<float> &returns) {
  auto sum = 0.0f;
  auto worst = 0.0f;
  auto current = 0.0f;

  for (auto r : returns) {
    sum += r;
    current += r;
    if (worst < current)
      worst = current;
    if (current > 0.0f)
      current = 0.0f;
  }

  return worst < 0.0f ? -sum / worst : numeric_limits<float>::infinity();
}

float ReturnToSlopeStDevRatio(const vector<float> &returns) {
  auto sum = 0.0f;
  for (auto r : returns)
    sum += r;

  auto slope = sum / returns.size();
  auto cum = 0.0f;
  auto devSum = 0.0f;
  for (size_t i = 0; i < returns.size(); ++i) {
    cum += returns[i];
    auto dev = slope * (i + 1) - cum;
    devSum += dev * dev;
  }

  return sum / sqrt(devSum);
}

function<float(const vector<float> &)>
CorrelationToBenchmark(const vector<float> &benchmark) {
  return [=](const vector<float> &returns) {
    return Correlation(returns, benchmark);
  };
}

ReturnStats GetStats(const std::vector<float> &returns,
                     const std::vector<std::vector<float>> &benchmarks) {
  ReturnStats s{};

  struct BenchVars {
    float mean, dotProd, devSum;
  };
  vector<BenchVars> benchVars(benchmarks.size());

  for (size_t j = 0; j < benchmarks.size(); ++j)
    benchVars[j].mean = Mean(benchmarks[j]);

  auto currentDrawdown = 0.0f;

  // first pass
  for (auto r : returns) {
    s.totalReturn += r;

    currentDrawdown += r;
    if (s.worstDrawdown < currentDrawdown)
      s.worstDrawdown = currentDrawdown;
    if (currentDrawdown > 0.0f)
      currentDrawdown = 0.0f;
  }
  auto mean = s.totalReturn / returns.size();
  auto devSum = 0.0f;
  auto cum = 0.0f;
  auto slopeDevSum = 0.0f;
  auto posDevSum = 0.0f;
  auto negDevSum = 0.0f;
  size_t posCount = 0;
  size_t negCount = 0;

  // second pass
  for (size_t i = 0; i < returns.size(); ++i) {
    auto r = returns[i];

    auto dev = r - mean;
    // deviation
    devSum += dev * dev;

    // positive and negative semi-deviations
    if (dev > 0) {
      posDevSum += dev * dev;
      ++posCount;
    } else if (dev < 0) {
      negDevSum += dev * dev;
      ++negCount;
    }

    // slope deviation (K-ratio)
    cum += r;
    auto slopeDev = mean * (i + 1) - cum;
    slopeDevSum += slopeDev * slopeDev;

    // benchmark correlations
    for (size_t j = 0; j < benchmarks.size(); ++j) {
      auto &v = benchVars[j];
      auto devB = benchmarks[j][i] - v.mean;
      v.dotProd += dev * devB;
      v.devSum += devB * devB;
    }
  }

  s.deviation = sqrt(devSum / returns.size());
  s.positiveDeviation = sqrt(posDevSum / posCount);
  s.negativeDeviation = sqrt(negDevSum / negCount);
  s.slopeDeviation = sqrt(slopeDevSum / returns.size());
  s.benchmarkCorrelations.resize(benchmarks.size());
  for (size_t j = 0; j < benchmarks.size(); ++j)
    s.benchmarkCorrelations[j] =
        benchVars[j].dotProd / sqrt(devSum * benchVars[j].devSum);

  return s;
}

float ScaleStats(const ReturnStats &s, const ReturnStats &r) {
  auto stats = pow(r.totalReturn, s.totalReturn) *
               pow(r.deviation, s.deviation) *
               pow(r.slopeDeviation, s.slopeDeviation) *
               pow(r.positiveDeviation, s.positiveDeviation) *
               pow(r.negativeDeviation, s.negativeDeviation) *
               pow(r.worstDrawdown, s.worstDrawdown);
  for (size_t i = 0; i < r.benchmarkCorrelations.size(); ++i)
    stats *= pow(r.benchmarkCorrelations[i], s.benchmarkCorrelations[i]);
  return stats;
}

float ScaleTargetStat(float s, float t, float r) {
	if (s == 0.0f)
		return 1.0f;
	if (!isnan(t)) {
		if (s > 0.0f) {
			if (r > t)
				r = t;
		}
		else {
			if (r < t)
				r = t;
		}
	}
	return pow(r, s);
}

float ScaleTargetedStats(
	const ReturnStats &s,
	const ReturnStats &t,
	const ReturnStats &r) {
  auto stats = 1.0f;
  stats *= ScaleTargetStat(s.totalReturn, t.totalReturn, r.totalReturn);
  stats *= ScaleTargetStat(s.deviation, t.deviation, r.deviation);
  stats *= ScaleTargetStat(s.slopeDeviation, t.slopeDeviation, r.slopeDeviation);
  stats *= ScaleTargetStat(s.positiveDeviation, t.positiveDeviation, r.positiveDeviation);
  stats *= ScaleTargetStat(s.negativeDeviation, t.negativeDeviation, r.negativeDeviation);
  stats *= ScaleTargetStat(s.worstDrawdown, t.worstDrawdown, r.worstDrawdown);

  for (size_t i = 0; i < r.benchmarkCorrelations.size(); ++i)
    stats *= pow(r.benchmarkCorrelations[i], s.benchmarkCorrelations[i]);
  return stats;
}

std::function<float(const std::vector<float> &)>
CustomRatio(const OptimizationParams &params,
            const std::vector<std::vector<float>> &benchmarks) {
  if (params.targets.isSet())
    return [=](const vector<float> &returns) {
      auto stats = GetStats(returns, benchmarks);
      return ScaleTargetedStats(params.factors, params.targets, stats);
    };
  return [=](const vector<float> &returns) {
    auto stats = GetStats(returns, benchmarks);
    return ScaleStats(params.factors, stats);
  };
}

float targetFactor(float result, float target, float devScale)
{
	return 1.f / (1 + abs(result - target) / devScale);
}

std::function<float(const std::vector<float> &)>
CustomVolTarget(const float targetVol) {
	return [=](const vector<float> &returns) {
		auto sum = Sum(returns);
		auto count = returns.size();
		auto mean = sum / count;
		auto sum2 = 0.0f;
		for (auto f : returns) {
			auto dev = f - mean;
			sum2 += dev*dev;
		}
		auto vol = sqrt(sum2 / count);
		
		return mean*targetFactor(vol, targetVol, 0.005);
	};
}

std::function<float(const std::vector<float> &)>
CustomVolTargetNormFactors(const float returnFactor, const float targetVol, const float skewFactor, const float kurtFactor) {
	return [=](const vector<float> &returns) {
		const auto s = getNormStats(returns);

		const auto returnResult = exp(pow(s.mean, returnFactor));
		const auto volResult = targetFactor(s.stdev, targetVol, 0.005);
		const auto skewResult = exp(pow(s.skew, skewFactor));
		const auto kurtResult = exp(pow(s.kurt, kurtFactor));

		return returnResult*volResult*skewResult*kurtResult;
	};
}
