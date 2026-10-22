// Social Links Configuration
// Values come from VITE_* environment variables; the fallbacks are the public
// contact details already shown on the site, so a build without a .env still works.
const env = import.meta.env;

const github = env.VITE_GITHUB_URL || 'https://github.com/nastehajelle';
const linkedin = env.VITE_LINKEDIN_URL || 'https://www.linkedin.com/in/nastehajelle/';
const email = env.VITE_EMAIL || 'jellenatsha@gmail.com';
const phone = env.VITE_PHONE || '+252613039180';
const whatsapp = env.VITE_WHATSAPP_URL || 'https://wa.me/252613039180';
const location = env.VITE_LOCATION || 'Mogadishu, Somalia';

export const socialLinks = {
  // Main social profiles
  github,
  linkedin,
  email,
  whatsapp,
  location,

  // GitHub repository URLs
  repositories: {
    projectOne: env.VITE_GITHUB_PROJECT1_URL || 'https://github.com/nastehajelle/POSTGRADUATE-ELMS',
    projectTwo: env.VITE_GITHUB_PROJECT2_URL || 'https://github.com/nastehajelle/survey-manegment-system',
  },

  // Formatted display names
  display: {
    github: github.replace('https://', ''),
    linkedin: linkedin.replace('https://', '').replace(/\/$/, ''),
    email,
    phone,
  },
};

export default socialLinks;
