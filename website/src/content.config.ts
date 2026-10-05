import { defineCollection } from 'astro:content';
import { z } from 'astro/zod';
import { glob } from 'astro/loaders';
import { docsLoader } from '@astrojs/starlight/loaders';
import { docsSchema } from '@astrojs/starlight/schema';

export const collections = {
	docs: defineCollection({ loader: docsLoader(), schema: docsSchema() }),
	guides: defineCollection({
		loader: glob({ pattern: '*.md', base: './src/content/guides' }),
		schema: z.object({
			title: z.string(),
			description: z.string(),
			order: z.number(),
		}),
	}),
};
