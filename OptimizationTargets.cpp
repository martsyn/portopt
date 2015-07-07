#include "stdafx.h"
#include "VectorMath.h"
#include "OptimizationTargets.h"

using namespace std;

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

  s.deviation = sqrt(devSum) / returns.size();
  s.positiveDeviation = sqrt(posDevSum) / posCount;
  s.negativeDeviation = sqrt(negDevSum) / negCount;
  s.slopeDeviation = sqrt(slopeDevSum) / returns.size();
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

std::function<float(const std::vector<float> &)>
CustomRatio(const ReturnStats &scales,
            const std::vector<std::vector<float>> &benchmarks) {
  return [=](const vector<float> &returns) {
    auto stats = GetStats(returns, benchmarks);
    return ScaleStats(scales, stats);
  };
}
