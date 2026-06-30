module.exports = ({ file }) => {
  const isLegacy = file?.basename === 'legacy.scss';

  return {
    plugins: {
      tailwindcss: isLegacy
          ? './assets/legacy/tailwind.legacy.config.js'
          : './tailwind.config.js',
      autoprefixer: {},
    },
  };
};
