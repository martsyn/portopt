#include "stdafx.h"
#include "InitialPointProvider.h"

using namespace std;


InitialPointProvider::InitialPointProvider(int dim, int steps)
	: dim(dim), state(dim), scale(1.0f / steps)
{
	state[0] = steps;
}

bool InitialPointProvider::Bump()
{
	int i = 0;
	while (state[i] == 0)
	{
		++i;
		if (i == dim - 1)
			return false;
	}
	++state[i + 1];
	int cum = 0;
	for (; i >= 0; --i)
	{
		cum += state[i];
		state[i] = i == 0 ? cum - 1 : 0;
	}
	return true;
}

bool InitialPointProvider::Pull(vector<float> &dest)
{
	for (int i = 0; i < dim; ++i)
		dest[i] = state[i] * scale;
	return Bump();
}
