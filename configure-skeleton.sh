#!/bin/bash

script_name=$(basename "$0")
git_name=$(git config user.name)
git_email=$(git config user.email)

read -p "Author name ($git_name): " author_name
author_name=${author_name:-$git_name}

read -p "Author email ($git_email): " author_email
author_email=${author_email:-$git_email}

username_guess=${author_name//[[:blank:]]/}
read -p "Author username ($username_guess): " author_username
author_username=${author_username:-$username_guess}

current_directory=$(pwd)
current_directory=$(basename "$current_directory")
read -p "Package name ($current_directory): " package_name
package_name=${package_name:-$current_directory}
# convert my-class-title to MyClassTitle - RODO: use to subsctitute ./src/*
class_name=$(echo "$package_name" | sed 's/[-_]/ /g' | awk '{for(j=1;j<=NF;j++){ $j=toupper(substr($j,1,1)) substr($j,2) }}1' | sed 's/\s//g')

read -p "Package description: " package_description
echo
echo -e "Author: $author_name ($author_username, $author_email)"
echo -e "Package: $package_name <$package_description>"

echo
echo "This script will replace the above values in all files in the project directory and reset the git repository."
read -p "Are you sure you wish to continue? (n/y) " -n 1 -r

echo
if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    [[ "$0" = "$BASH_SOURCE" ]] && exit 1 || return 1
fi

echo
files=$(egrep -r -l ":author|:package" ./*  | grep -v "$script_name")

for file in $files ; do
    echo "Customising file $file"
    temp_file="$file.temp"
    < "$file" \
      sed "s/:author_name/$author_name/g" \
    | sed "s/:author_username/$author_username/g" \
    | sed "s/:author_email/$author_email/g" \
    | sed "s/:package_name/$package_name/g" \
    | sed "s/:package_description/$package_description/g" \
    | sed "/^\*\*Note:\*\* Replace/d" \
    > "$temp_file"
    rm -f "$file"
    mv "$temp_file" "$file"
done

echo "Replaced all values and reset git directory, self destructing in 3 seconds ..."
sleep 3
rm -- "$0"
