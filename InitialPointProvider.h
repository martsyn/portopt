#pragma once
class InitialPointProvider
{
	int dim;
	std::vector<int> state;
	const float scale;
public:
	InitialPointProvider(int dim, int steps);
	bool Pull(std::vector<float>& dest);

private:

	bool Bump();
	void operator=(const InitialPointProvider&) = delete;
};

