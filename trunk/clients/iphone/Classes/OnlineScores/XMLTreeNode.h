//
//  XMLTreeNode.h
//  daypack
//
//  Created by John Stump on 2/15/09.
//  Copyright 2009 John Stump. All rights reserved.
//

#import <Foundation/Foundation.h>


@interface XMLTreeNode : NSObject {
	XMLTreeNode* parent;
	NSString* name;	// element name ("pages", "belongings", etc)
	NSMutableDictionary* attributes;
	NSMutableString* text;	// from foundCharacters()
	NSMutableDictionary* children;	// key is child's element name, value is NSMutableArray of tree nodes
}

@property (nonatomic,retain) XMLTreeNode* parent;
@property (nonatomic,retain) NSString* name;
@property (nonatomic,retain) NSDictionary* attributes;
@property (nonatomic,retain) NSMutableString* text;
@property (nonatomic,retain) NSMutableDictionary* children;

- (XMLTreeNode*) init;
- (void) dealloc;

// this will return an array of children matching the given element name
- (NSArray*) findChildren:(NSString*) name;

// this will return the child node with the given name 
// (will return the 1st occurrence if there are more)
- (XMLTreeNode*) findChild:(NSString*) name;

// this will return the nth child with the given name
- (XMLTreeNode*) findChild:(NSString*) name at:(NSInteger) position;

- (NSString*) description;

@end
