// Resolve a file in public/ against the deploy base path ("/" on Vercel, "/NDC/" on GitHub Pages)
export function publicUrl(path: string) {
  return import.meta.env.BASE_URL + path.replace(/^\//, '');
}
