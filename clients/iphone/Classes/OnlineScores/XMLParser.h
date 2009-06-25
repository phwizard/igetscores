//
//  XMLParser.h
//  TPS
//
//  Created by Andrew Kopanev on 3/23/09.
//  Copyright 2009 Injoit.com. All rights reserved.
//

#import <Foundation/Foundation.h>
#import <UIKit/UIKit.h>

@interface XMLParser : NSObject {
	NSMutableArray *scoresArray;
	NSMutableDictionary *answer;
	NSInteger scoresCount;
	
	//temporary variables
	NSMutableString *currentStringValue;
	NSString *currentElementName;
	NSMutableDictionary *currentScore;
}

@property NSInteger scoresCount;
@property (nonatomic, retain) NSMutableArray *scoresArray;
@property (nonatomic, retain) NSMutableDictionary *answer;

- (void)parseXMLFileAtURL:(NSString *)URL parseError:(NSError **)error;
- (void)parseXMLwithData:(NSData*)data parseError:(NSError **)error;

@end