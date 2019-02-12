if [ -f compropago.zip ]; then
    echo Delete old file
    rm compropago.zip
fi

echo Remove .DS_Store files
find . -name .DS_Store -print0 | xargs -0 git rm -f --ignore-unmatch

echo Building zip plugin
zip -r compropago.zip . -x "*.git*" ".DS_Store" "build.sh"
